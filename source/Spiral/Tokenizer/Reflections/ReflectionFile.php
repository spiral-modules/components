<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer\Reflections;

use Spiral\Core\Component;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Tokenizer\ReflectionFileInterface;
use Spiral\Tokenizer\TokenizerInterface;

/**
 * File reflections can fetch information about classes, interfaces, functions and traits declared
 * in file. In addition file reflection provides ability to fetch and describe every method/function
 * call.
 */
class ReflectionFile extends Component implements ReflectionFileInterface
{
    /**
     * Development sugar.
     */
    use SaturateTrait;

    /**
     * Namespace separator.
     */
    const NS_SEPARATOR = '\\';

    /**
     * Constants for convenience.
     */
    const TOKEN_TYPE = TokenizerInterface::TYPE;
    const TOKEN_CODE = TokenizerInterface::CODE;
    const TOKEN_LINE = TokenizerInterface::LINE;

    /**
     * Opening and closing token ids.
     */
    const O_TOKEN = 0;
    const C_TOKEN = 1;

    /**
     * Namespace uses.
     */
    const N_USES = 2;

    /**
     * Set of tokens required to detect classes, traits, interfaces and function declarations. We
     * don't need any other token for that.
     *
     * @var array
     */
    static private $processTokens = [
        '{',
        '}',
        ';',
        T_PAAMAYIM_NEKUDOTAYIM,
        T_NAMESPACE,
        T_STRING,
        T_CLASS,
        T_INTERFACE,
        T_TRAIT,
        T_FUNCTION,
        T_NS_SEPARATOR,
        T_INCLUDE,
        T_INCLUDE_ONCE,
        T_REQUIRE,
        T_REQUIRE_ONCE,
        T_USE,
        T_AS
    ];

    /**
     * @var string
     */
    private $filename = '';

    /**
     * Parsed tokens array.
     *
     * @invisible
     * @var array
     */
    private $tokens = [];

    /**
     * Total tokens count.
     *
     * @invisible
     * @var int
     */
    private $countTokens = 0;

    /**
     * Indicator that file has external includes.
     *
     * @invisible
     * @var bool
     */
    private $hasIncludes = false;

    /**
     * Namespaces used in file and their token positions.
     *
     * @invisible
     * @var array
     */
    private $namespaces = [];

    /**
     * Declarations of classes, interfaces and traits.
     *
     * @invisible
     * @var array
     */
    private $declarations = [];

    /**
     * Declarations of new functions.
     *
     * @invisible
     * @var array
     */
    private $functions = [];

    /**
     * Every found method/function invocation.
     *
     * @invisible
     * @var ReflectionInvocation[]
     */
    private $invocations = [];

    /**
     * @invisible
     * @var TokenizerInterface
     */
    protected $tokenizer = null;

    /**
     * @param string             $filename
     * @param TokenizerInterface $tokenizer
     * @param array              $cache     Tokenizer can construct reflection with pre-created
     *                                      cache to speed up indexation.
     */
    public function __construct($filename, TokenizerInterface $tokenizer = null, array $cache = [])
    {
        $this->filename = $filename;
        $this->tokenizer = $this->saturate($tokenizer, TokenizerInterface::class);

        if (!empty($cache)) {
            //Locating file schema from file, can speed up class location a LOT
            $this->importSchema($cache);

            return;
        }

        //Looking for declarations
        $this->locateDeclarations();
    }

    /**
     * {@inheritdoc}
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array_keys($this->functions);
    }

    /**
     * {@inheritdoc}
     */
    public function getClasses()
    {
        if (!isset($this->declarations['T_CLASS'])) {
            return [];
        }

        return array_keys($this->declarations['T_CLASS']);
    }

    /**
     * {@inheritdoc}
     */
    public function getTraits()
    {
        if (!isset($this->declarations['T_TRAIT'])) {
            return [];
        }

        return array_keys($this->declarations['T_TRAIT']);
    }

    /**
     * {@inheritdoc}
     */
    public function getInterfaces()
    {
        if (!isset($this->declarations['T_INTERFACE'])) {
            return [];
        }

        return array_keys($this->declarations['T_INTERFACE']);
    }

    /**
     * Get list of tokens associated with given file.
     *
     * @return array
     */
    public function getTokens()
    {
        if (empty($this->tokens)) {
            //Happens when reflection created from cache
            $this->tokens = $this->tokenizer->fetchTokens($this->filename);
            $this->countTokens = count($this->tokens);
        }

        return $this->tokens;
    }

    /**
     * {@inheritdoc}
     */
    public function hasIncludes()
    {
        return $this->hasIncludes;
    }

    /**
     * {@inheritdoc}
     */
    public function getInvocations()
    {
        if (empty($this->invocations)) {
            $this->locateInvocations($this->getTokens());
        }

        return $this->invocations;
    }

    /**
     * Export found declaration as array for caching purposes.
     *
     * @return array
     */
    public function exportSchema()
    {
        return [$this->hasIncludes, $this->declarations, $this->functions, $this->namespaces];
    }

    /**
     * Import cached reflection schema.
     *
     * @param array $cache
     */
    protected function importSchema(array $cache)
    {
        list($this->hasIncludes, $this->declarations, $this->functions, $this->namespaces) = $cache;
    }

    /**
     * Locate every class, interface, trait or function definition.
     */
    protected function locateDeclarations()
    {
        foreach ($this->getTokens() as $tokenID => $token) {
            if (!in_array($token[self::TOKEN_TYPE], self::$processTokens)) {
                continue;
            }

            switch ($token[self::TOKEN_TYPE]) {
                case T_NAMESPACE:
                    $this->registerNamespace($tokenID);
                    break;

                case T_USE:
                    $this->registerUse($tokenID);
                    break;

                case T_FUNCTION:
                    $this->registerFunction($tokenID);
                    break;

                case T_CLASS:
                case T_TRAIT;
                case T_INTERFACE:
                    if (
                        $this->tokens[$tokenID][self::TOKEN_TYPE] == T_CLASS
                        && isset($this->tokens[$tokenID - 1])
                        && $this->tokens[$tokenID - 1][self::TOKEN_TYPE] == T_PAAMAYIM_NEKUDOTAYIM
                    ) {
                        //PHP5.5 ClassName::class constant
                        continue;
                    }

                    $this->registerDeclaration($tokenID, $token[self::TOKEN_TYPE]);
                    break;

                case T_INCLUDE:
                case T_INCLUDE_ONCE:
                case T_REQUIRE:
                case T_REQUIRE_ONCE:
                    $this->hasIncludes = true;
            }
        }

        //Dropping empty namespace
        if (isset($this->namespaces[''])) {
            $this->namespaces['\\'] = $this->namespaces[''];
            unset($this->namespaces['']);
        }
    }

    /**
     * Handle namespace declaration.
     *
     * @param int $tokenID
     */
    private function registerNamespace($tokenID)
    {
        $namespace = '';
        $localID = $tokenID + 1;

        do {
            $token = $this->tokens[$localID++];
            if ($token[self::TOKEN_CODE] == '{') {
                break;
            }

            $namespace .= $token[self::TOKEN_CODE];
        } while (
            isset($this->tokens[$localID])
            && $this->tokens[$localID][self::TOKEN_CODE] != '{'
            && $this->tokens[$localID][self::TOKEN_CODE] != ';'
        );

        //Whitespaces
        $namespace = trim($namespace);

        $uses = [];
        if (isset($this->namespaces[$namespace])) {
            $uses = $this->namespaces[$namespace];
        }

        if ($this->tokens[$localID][self::TOKEN_CODE] == ';') {
            $endingID = count($this->tokens) - 1;
        } else {
            $endingID = $this->endingToken($tokenID);
        }

        $this->namespaces[$namespace] = [
            self::O_TOKEN => $tokenID,
            self::C_TOKEN => $endingID,
            self::N_USES  => $uses
        ];
    }

    /**
     * Handle use (import class from another namespace).
     *
     * @param int $tokenID
     */
    private function registerUse($tokenID)
    {
        $namespace = rtrim($this->activeNamespace($tokenID), '\\');

        $class = '';
        $localAlias = null;
        for ($localID = $tokenID + 1; $this->tokens[$localID][self::TOKEN_CODE] != ';'; $localID++) {
            if ($this->tokens[$localID][self::TOKEN_TYPE] == T_AS) {
                $localAlias = '';
                continue;
            }

            if ($localAlias === null) {
                $class .= $this->tokens[$localID][self::TOKEN_CODE];
            } else {
                $localAlias .= $this->tokens[$localID][self::TOKEN_CODE];
            }
        }

        if (empty($localAlias)) {
            $names = explode('\\', $class);
            $localAlias = end($names);
        }

        $this->namespaces[$namespace][self::N_USES][trim($localAlias)] = trim($class);
    }

    /**
     * Handle function declaration (function creation).
     *
     * @param int $tokenID
     */
    private function registerFunction($tokenID)
    {
        foreach ($this->declarations as $declarations) {
            foreach ($declarations as $location) {
                if ($tokenID >= $location[self::O_TOKEN] && $tokenID <= $location[self::C_TOKEN]) {
                    //We are inside class, function is method
                    return;
                }
            }
        }

        $localID = $tokenID + 1;
        while ($this->tokens[$localID][self::TOKEN_TYPE] != T_STRING) {
            //Fetching function name
            $localID++;
        }

        $name = $this->tokens[$localID][self::TOKEN_CODE];
        if (!empty($namespace = $this->activeNamespace($tokenID))) {
            $name = $namespace . self::NS_SEPARATOR . $name;
        }

        $this->functions[$name] = [
            self::O_TOKEN => $tokenID,
            self::C_TOKEN => $this->endingToken($tokenID)
        ];
    }

    /**
     * Handle declaration of class, trait of interface. Declaration will be stored under it's token
     * type in declarations array.
     *
     * @param int $tokenID
     * @param int $tokenType
     */
    private function registerDeclaration($tokenID, $tokenType)
    {
        $localID = $tokenID + 1;
        while ($this->tokens[$localID][self::TOKEN_TYPE] != T_STRING) {
            $localID++;
        }

        $name = $this->tokens[$localID][self::TOKEN_CODE];
        if (!empty($namespace = $this->activeNamespace($tokenID))) {
            $name = $namespace . self::NS_SEPARATOR . $name;
        }

        $this->declarations[token_name($tokenType)][$name] = [
            self::O_TOKEN => $tokenID,
            self::C_TOKEN => $this->endingToken($tokenID)
        ];
    }

    /**
     * Locate every function or static method call (including $this calls).
     *
     * @param array $tokens
     * @param int   $invocationLevel
     */
    private function locateInvocations(array $tokens, $invocationLevel = 0)
    {
        //Multiple "(" and ")" statements nested.
        $level = 0;

        //Inside array arguments
        $arrayLevel = 0;

        //Skip all tokens until next function
        $ignore = false;

        //Were function was found
        $invocationTID = 0;

        //Parsed arguments and their first token id
        $arguments = [];
        $argumentsTID = false;

        //Tokens used to re-enable token detection
        $stopTokens = [T_STRING, T_WHITESPACE, T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NS_SEPARATOR];
        foreach ($tokens as $tokenID => $token) {
            $tokenType = $token[self::TOKEN_TYPE];

            //We are not indexing function declarations or functions called from $objects.
            if ($tokenType == T_FUNCTION || $tokenType == T_OBJECT_OPERATOR || $tokenType == T_NEW) {
                if (
                    empty($argumentsTID)
                    && (
                        empty($invocationTID)
                        || $this->getSource($invocationTID, $tokenID - 1) != '$this'
                    )
                ) {
                    //Not a call, function declaration, or object method
                    $ignore = true;
                    continue;
                }
            } elseif ($ignore) {
                if (!in_array($tokenType, $stopTokens)) {
                    //Returning to search
                    $ignore = false;
                }
                continue;
            }

            //We are inside function, and there is "(", indexing arguments.
            if (!empty($invocationTID) && ($tokenType == '(' || $tokenType == '[')) {
                if (empty($argumentsTID)) {
                    $argumentsTID = $tokenID;
                }

                $level++;
                if ($level != 1) {
                    //Not arguments beginning, but arguments part
                    $arguments[$tokenID] = $token;
                }

                continue;
            }

            //We are inside function arguments and ")" met.
            if (!empty($invocationTID) && ($tokenType == ')' || $tokenType == ']')) {
                $level--;
                if ($level == -1) {
                    $invocationTID = false;
                    $level = 0;
                    continue;
                }

                //Function fully indexed, we can process it now.
                if ($level == 0) {
                    $this->registerInvocation(
                        $invocationTID,
                        $argumentsTID,
                        $tokenID,
                        $arguments,
                        $invocationLevel
                    );

                    //Closing search
                    $arguments = [];
                    $argumentsTID = $invocationTID = false;
                } else {
                    //Not arguments beginning, but arguments part
                    $arguments[$tokenID] = $token;
                }

                continue;
            }

            //Still inside arguments.
            if (!empty($invocationTID) && !empty($level)) {
                $arguments[$tokenID] = $token;
                continue;
            }

            //Nothing valuable to remember, will be parsed later.
            if (!empty($invocationTID) && in_array($tokenType, $stopTokens)) {
                continue;
            }

            //Seems like we found function/method call
            if (
                $tokenType == T_STRING
                || $tokenType == T_STATIC
                || $tokenType == T_NS_SEPARATOR
                || ($tokenType == T_VARIABLE && $token[self::TOKEN_CODE] == '$this')
            ) {
                $invocationTID = $tokenID;
                $level = 0;

                $argumentsTID = false;
                continue;
            }

            //Returning to search
            $invocationTID = false;
            $arguments = [];
        }
    }

    /**
     * Registering invocation.
     *
     * @param int   $invocationID
     * @param int   $argumentsID
     * @param int   $endID
     * @param array $arguments
     * @param int   $invocationLevel
     */
    private function registerInvocation(
        $invocationID,
        $argumentsID,
        $endID,
        array $arguments,
        $invocationLevel
    ) {
        //Nested invocations
        $this->locateInvocations($arguments, $invocationLevel + 1);

        list($class, $operator, $name) = $this->fetchContext($invocationID, $argumentsID);
        if (!empty($operator) && empty($class)) {
            //Non detectable
            return;
        }

        $this->invocations[] = new ReflectionInvocation(
            $this->filename,
            $this->lineNumber($invocationID),
            $class,
            $operator,
            $name,
            ReflectionArgument::locateArguments($arguments),
            $this->getSource($invocationID, $endID),
            $invocationLevel
        );
    }

    /**
     * Fetching invocation context.
     *
     * @param int $invocationTID
     * @param int $argumentsTID
     * @return array
     */
    private function fetchContext($invocationTID, $argumentsTID)
    {
        $class = $operator = '';
        $name = trim($this->getSource($invocationTID, $argumentsTID), '( ');

        //Let's try to fetch all information we need
        if (strpos($name, '->') !== false) {
            $operator = '->';
        } elseif (strpos($name, '::') !== false) {
            $operator = '::';
        }

        if (!empty($operator)) {
            list($class, $name) = explode($operator, $name);

            //We now have to clarify class name
            if (in_array($class, ['self', 'static', '$this'])) {
                $class = $this->activeDeclaration($invocationTID);
            }
        }

        return [$class, $operator, $name];
    }

    /**
     * Get declaration which is active in given token position.
     *
     * @param int $tokenID
     * @return string|null
     */
    private function activeDeclaration($tokenID)
    {
        foreach ($this->declarations as $declarations) {
            foreach ($declarations as $name => $position) {
                if ($tokenID >= $position[self::O_TOKEN] && $tokenID <= $position[self::C_TOKEN]) {
                    return $name;
                }
            }
        }

        //Can not be detected
        return null;
    }

    /**
     * Get namespace name active at specified token position.
     *
     * @param int $tokenID
     * @return string
     */
    private function activeNamespace($tokenID)
    {
        foreach ($this->namespaces as $namespace => $position) {
            if ($tokenID >= $position[self::O_TOKEN] && $tokenID <= $position[self::C_TOKEN]) {
                return $namespace;
            }
        }

        //Seems like no namespace declaration
        $this->namespaces[''] = [
            self::O_TOKEN => 0,
            self::C_TOKEN => count($this->tokens),
            self::N_USES  => []
        ];

        return '';
    }

    /**
     * Find token ID of ending brace.
     *
     * @param int $tokenID
     * @return mixed
     */
    private function endingToken($tokenID)
    {
        $level = null;
        for ($localID = $tokenID; $localID < $this->countTokens; $localID++) {
            $token = $this->tokens[$localID];
            if ($token[self::TOKEN_CODE] == '{') {
                $level++;
                continue;
            }

            if ($token[self::TOKEN_CODE] == '}') {
                $level--;
            }

            if ($level === 0) {
                break;
            }
        }

        return $localID;
    }

    /**
     * Get line number associated with token.
     *
     * @param int $tokenID
     * @return int
     */
    private function lineNumber($tokenID)
    {
        while (empty($this->tokens[$tokenID][self::TOKEN_LINE])) {
            $tokenID--;
        }

        return $this->tokens[$tokenID][self::TOKEN_LINE];
    }

    /**
     * Get source located between two tokens.
     *
     * @param int $startID
     * @param int $endID
     * @return string
     */
    private function getSource($startID, $endID)
    {
        $result = '';
        for ($tokenID = $startID; $tokenID <= $endID; $tokenID++) {
            //Collecting function usage source
            $result .= $this->tokens[$tokenID][self::TOKEN_CODE];
        }

        return $result;
    }
}