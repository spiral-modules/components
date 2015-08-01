<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Tokenizer\Reflections;

use Spiral\Core\Component;
use Spiral\Tokenizer\TokenizerInterface;

/**
 * File reflections can fetch information about classes, interfaces, functions and traits declared
 * in file. In addition file reflection provides ability to fetch and describe every method/function
 * call.
 */
class ReflectionFile extends Component
{
    /**
     * Constants for convenience.
     */
    const TOKEN_TYPE = TokenizerInterface::TYPE;
    const TOKEN_CODE = TokenizerInterface::CODE;
    const TOKEN_LINE = TokenizerInterface::LINE;
    const TOKEN_ID   = 3;

    /**
     * Set of tokens required to detect classes, traits, interfaces and function declarations. We
     * don't need any other token for that.
     *
     * @var array
     */
    static private $useTokens = [
        '{', '}', ';', T_PAAMAYIM_NEKUDOTAYIM, T_NAMESPACE, T_STRING, T_CLASS, T_INTERFACE, T_TRAIT,
        T_FUNCTION, T_NS_SEPARATOR, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_USE, T_AS
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
     * @var int
     */
    private $countTokens = 0;

    /**
     * Indicator that file has external includes.
     *
     * @var bool
     */
    private $hasIncludes = false;

    /**
     * Namespaces used in file and their token positions.
     *
     * @var array
     */
    private $namespaces = [];

    /**
     * Declarations of classes, interfaces and traits.
     *
     * @var array
     */
    private $declarations = [];

    /**
     * Declarations of new functions.
     *
     * @var array
     */
    private $functions = [];

    /**
     * Every found method/function call.
     *
     * @var ReflectionCall[]
     */
    private $calls = [];

    /**
     * @invisible
     * @var TokenizerInterface
     */
    protected $tokenizer = null;

    /**
     * New instance of file reflection.
     *
     * @param TokenizerInterface $tokenizer
     * @param string             $filename
     * @param array              $cache     Tokenizer can construct reflection with pre-created
     *                                      cache to speed up indexation.
     */
    public function __construct(TokenizerInterface $tokenizer, $filename, array $cache = [])
    {
        $this->tokenizer = $tokenizer;
        $this->filename = $filename;

        //        if (!empty($cache))
        //        {
        //            $this->importSchema($cache);
        //
        //            return;
        //        }

        //Getting tokens list
        $tokens = $tokenizer->fetchTokens($filename);

        //Let's erase not useful tokens
        $this->tokens = $this->cleanTokens($tokens);
        $this->countTokens = count($this->tokens);

        //Looking for declarations
        $this->locateDeclarations();

        //No need to record empty namespace
        if (isset($this->namespaces['']))
        {
            $this->namespaces['\\'] = $this->namespaces[''];
            unset($this->namespaces['']);
        }

        //Restoring original tokens
        $this->tokens = $tokens;
        $this->countTokens = count($this->tokens);
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
     * Reflection filename.
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * List of declared functions.
     *
     * @return array
     */
    public function getFunctions()
    {
        return array_keys($this->functions);
    }

    /**
     * List of declared classes.
     *
     * @return array
     */
    public function getClasses()
    {
        if (!isset($this->declarations['T_CLASS']))
        {
            return [];
        }

        return array_keys($this->declarations['T_CLASS']);
    }

    /**
     * List of declared traits.
     *
     * @return array
     */
    public function getTraits()
    {
        if (!isset($this->declarations['T_TRAIT']))
        {
            return [];
        }

        return array_keys($this->declarations['T_TRAIT']);
    }

    /**
     * List of declared interfaces.
     *
     * @return array
     */
    public function getInterfaces()
    {
        if (!isset($this->declarations['T_INTERFACE']))
        {
            return [];
        }

        return array_keys($this->declarations['T_INTERFACE']);
    }

    /**
     * Indication that file contains require/include statements.
     *
     * @return bool
     */
    public function hasIncludes()
    {
        return $this->hasIncludes;
    }

    /**
     * Locate and return list of every method or function call in specified file. Only static class
     * methods will be indexed.
     *
     * @return ReflectionCall[]
     */
    public function getCalls()
    {
        if (empty($this->calls))
        {
            $this->locateCalls($this->tokens ?: $this->tokenizer->fetchTokens($this->filename));
        }

        return $this->calls;
    }

    /**
     * Locate every class, interface, trait or function definition.
     */
    protected function locateDeclarations()
    {
        foreach ($this->tokens as $TID => $token)
        {
            switch ($token[self::TOKEN_TYPE])
            {
                case T_NAMESPACE:
                    $this->registerNamespace($TID);
                    break;

                case T_USE:
                    $this->registerUse($TID);
                    break;

                case T_FUNCTION:
                    $this->registerFunction($TID);
                    break;

                case T_CLASS:
                case T_TRAIT;
                case T_INTERFACE:
                    if (
                        $this->tokens[$TID][self::TOKEN_TYPE] == T_CLASS
                        && isset($this->tokens[$TID - 1])
                        && $this->tokens[$TID - 1][self::TOKEN_TYPE] == T_PAAMAYIM_NEKUDOTAYIM
                    )
                    {
                        echo 1;
                        //PHP5.5 ClassName::class constant
                        continue;
                    }

                    $this->registerDeclaration($TID, $token[self::TOKEN_TYPE]);
                    break;

                case T_INCLUDE:
                case T_INCLUDE_ONCE:
                case T_REQUIRE:
                case T_REQUIRE_ONCE:
                    $this->hasIncludes = true;
            }
        }
    }

    /**
     * Remove unnecessary for analysis tokens.
     *
     * @param array $tokens
     * @return array
     */
    private function cleanTokens(array $tokens)
    {
        $result = [];
        foreach ($tokens as $TID => $token)
        {
            if (!in_array($token[self::TOKEN_TYPE], self::$useTokens))
            {
                continue;
            }

            $token[self::TOKEN_ID] = $TID;
            $result[] = $token;
        }

        return $result;
    }

    /**
     * Import cached reflection schema.
     *
     * @param array $cache
     */
    private function importSchema(array $cache)
    {
        list($this->includes, $this->declarations, $this->functions, $this->namespaces) = $cache;
    }

    /**
     * Handle namespace declaration.
     *
     * @param int $firstTID
     * @return array
     */
    private function registerNamespace($firstTID)
    {
        $namespace = '';
        $TID = $firstTID + 1;

        do
        {
            $token = $this->tokens[$TID++];
            if ($token[self::TOKEN_CODE] == '{')
            {
                break;
            }

            $namespace .= $token[self::TOKEN_CODE];
        }
        while (
            isset($this->tokens[$TID])
            && $this->tokens[$TID][self::TOKEN_CODE] != '{'
            && $this->tokens[$TID][self::TOKEN_CODE] != ';'
        );

        $uses = [];
        if (isset($this->namespaces[$namespace]))
        {
            $uses = $this->namespaces[$namespace];
        }

        if ($this->tokens[$TID][self::TOKEN_CODE] == ';')
        {
            return $this->namespaces[$namespace] = [
                'firstTID' => $this->tokens[$firstTID][self::TOKEN_ID],
                'lastTID'  => $this->tokens[count($this->tokens) - 1][self::TOKEN_ID],
                'uses'     => $uses
            ];
        }

        //Declared using { and }
        return $this->namespaces[$namespace] = [
            'firstTID' => $this->tokens[$firstTID][self::TOKEN_ID],
            'lastTID'  => $this->endingTID($firstTID),
            'uses'     => $uses
        ];
    }

    /**
     * Handle function declaration (function creation).
     *
     * @param int $firstTID
     */
    private function registerFunction($firstTID)
    {
        foreach ($this->declarations as $declarations)
        {
            foreach ($declarations as $location)
            {
                $tokenID = $this->tokens[$firstTID][self::TOKEN_ID];
                if ($tokenID >= $location['firstTID'] && $tokenID <= $location['lastTID'])
                {
                    //We are inside class, function is method
                    return;
                }
            }
        }

        $name = $this->tokens[$firstTID + 1][self::TOKEN_CODE];
        $this->functions[$this->detectNamespace($firstTID) . $name] = [
            'firstTID' => $this->tokens[$firstTID][self::TOKEN_ID],
            'lastTID'  => $this->endingTID($firstTID)
        ];
    }

    /**
     * Handle declaration of class, trait of interface. Declaration will be stored under it's token
     * type in declarations array.
     *
     * @param int $firstTID
     * @param int $tokenType
     */
    private function registerDeclaration($firstTID, $tokenType)
    {
        $name = $this->tokens[$firstTID + 1][self::TOKEN_CODE];
        $this->declarations[token_name($tokenType)][$this->detectNamespace($firstTID) . $name] = [
            'firstTID' => $this->tokens[$firstTID][self::TOKEN_ID],
            'lastTID'  => $this->endingTID($firstTID)
        ];
    }

    /**
     * Handle use (import class from another namespace).
     *
     * @param int $firstTID
     */
    private function registerUse($firstTID)
    {
        $namespace = rtrim($this->detectNamespace($firstTID), '\\');

        $class = '';
        $localAlias = null;
        for ($TID = $firstTID + 1; $this->tokens[$TID][self::TOKEN_CODE] != ';'; $TID++)
        {
            if ($this->tokens[$TID][self::TOKEN_TYPE] == T_AS)
            {
                $localAlias = '';
                continue;
            }

            if ($localAlias === null)
            {
                $class .= $this->tokens[$TID][self::TOKEN_CODE];
            }
            else
            {
                $localAlias .= $this->tokens[$TID][self::TOKEN_CODE];
            }
        }

        if (empty($localAlias))
        {
            $names = explode('\\', $class);
            $localAlias = end($names);
        }

        $this->namespaces[$namespace]['uses'][$localAlias] = $class;
    }

    /**
     * Find token ID of ending brace.
     *
     * @param int $firstTID
     * @return mixed
     */
    private function endingTID($firstTID)
    {
        $level = null;
        for ($TID = $firstTID; $TID < $this->countTokens; $TID++)
        {
            $token = $this->tokens[$TID];
            if ($token[self::TOKEN_CODE] == '{')
            {
                $level++;
                continue;
            }

            if ($token[self::TOKEN_CODE] == '}')
            {
                $level--;
            }

            if ($level === 0)
            {
                break;
            }
        }

        return isset($this->tokens[$TID][self::TOKEN_ID]) ? $this->tokens[$TID][self::TOKEN_ID] : $TID;
    }

    /**
     * Locate every function or static method call.
     *
     * @param array $tokens
     * @param int   $functionLevel Internal constant used for parsing.
     */
    private function locateCalls(array $tokens, $functionLevel = 0)
    {
        //Multiple "(" and ")" statements nested.
        $parenthesisLevel = 0;

        //Skip all tokens until next function
        $skipTokens = false;

        //Were function was found
        $functionTID = $line = 0;

        //Parsed arguments and their first token id
        $arguments = [];
        $argumentsTID = false;

        //Tokens used to re-enable token detection
        $stopTokens = [T_STRING, T_WHITESPACE, T_DOUBLE_COLON, T_NS_SEPARATOR];
        foreach ($tokens as $TID => $token)
        {
            $tokenType = $token[self::TOKEN_TYPE];

            //We are not indexing function declarations or functions called from $objects.
            if ($tokenType == T_FUNCTION || $tokenType == T_OBJECT_OPERATOR || $tokenType == T_NEW)
            {
                //Not a call, function declaration, or object method
                if (!$argumentsTID)
                {
                    $skipTokens = true;
                    continue;
                }
            }
            elseif ($skipTokens)
            {
                if (!in_array($tokenType, $stopTokens))
                {
                    //Returning to search
                    $skipTokens = false;
                }
                continue;
            }

            //We are inside function, and there is "(", indexing arguments.
            if ($functionTID && $tokenType == '(')
            {
                if (!$argumentsTID)
                {
                    $argumentsTID = $TID;
                }

                $parenthesisLevel++;
                if ($parenthesisLevel != 1)
                {
                    //Not arguments beginning, but arguments part
                    $arguments[$TID] = $token;
                }

                continue;
            }

            //We are inside function arguments and ")" met.
            if ($functionTID && $tokenType == ')')
            {
                $parenthesisLevel--;
                if ($parenthesisLevel == -1)
                {
                    $functionTID = false;
                    $parenthesisLevel = 0;

                    continue;
                }

                //Function fully indexed, we can process it now.
                if (!$parenthesisLevel)
                {
                    $source = '';
                    for ($tokenID = $functionTID; $tokenID <= $TID; $tokenID++)
                    {
                        //Collecting function usage source
                        $source .= $tokens[$tokenID][self::TOKEN_CODE];
                    }

                    //Will be fixed in future
                    $class = $this->fetchClass($tokens, $functionTID, $argumentsTID);

                    $call = null;
                    if ($class != 'self' && $class != 'static')
                    {
                        $call = new ReflectionCall(
                            $this->filename,
                            $line,
                            $class,
                            $this->fetchName($tokens, $functionTID, $argumentsTID),
                            ReflectionArgument::fetchArguments($arguments),
                            $source,
                            $functionTID,
                            $TID,
                            $functionLevel
                        );
                    }

                    //Nested functions can be function in usage arguments.
                    $this->locateCalls($arguments, $functionLevel + 1);
                    !empty($call) && ($this->calls[] = $call);

                    //Closing search
                    $arguments = [];
                    $argumentsTID = $functionTID = false;
                }
                else
                {
                    //Not arguments beginning, but arguments part
                    $arguments[$TID] = $token;
                }

                continue;
            }

            //Still inside arguments.
            if ($functionTID && $parenthesisLevel)
            {
                $arguments[$TID] = $token;
                continue;
            }

            //Nothing valuable to remember, will be parsed later.
            if ($functionTID && in_array($tokenType, $stopTokens))
            {
                continue;
            }

            //Seems like we found function/method call
            if ($tokenType == T_STRING || $tokenType == T_STATIC || $tokenType == T_NS_SEPARATOR)
            {
                $functionTID = $TID;
                $line = $token[self::TOKEN_LINE];

                $parenthesisLevel = 0;
                $argumentsTID = false;
                continue;
            }

            //Returning to search
            $functionTID = false;
            $arguments = [];
        }
    }

    /**
     * Fetch method/function name.
     *
     * @param array $tokens
     * @param int   $callTID      call open token ID.
     * @param int   $argumentsTID Call first argument token ID.
     * @return string
     */
    private function fetchName(array $tokens, $callTID, $argumentsTID)
    {
        $function = [];
        for (; $callTID < $argumentsTID; $callTID++)
        {
            $token = $tokens[$callTID];
            if ($token[self::TOKEN_TYPE] == T_STRING || $token[self::TOKEN_TYPE] == T_STATIC)
            {
                $function[] = $token[self::TOKEN_CODE];
            }
        }

        if (count($function) > 1)
        {
            return end($function);
        }

        return $function[0];
    }

    /**
     * Fetch method parent class.
     *
     * @param array $tokens
     * @param int   $callTID      call open token ID.
     * @param int   $argumentsTID Call first argument token ID.
     * @return string
     */
    private function fetchClass(array $tokens, $callTID, $argumentsTID)
    {
        $function = [];
        for (; $callTID < $argumentsTID; $callTID++)
        {
            $token = $tokens[$callTID];
            if ($token[self::TOKEN_TYPE] == T_STRING || $token[self::TOKEN_TYPE] == T_STATIC)
            {
                $function[] = $token[self::TOKEN_CODE];
            }
        }

        if (count($function) == 1)
        {
            return null;
        }

        unset($function[count($function) - 1]);

        //Resolving class
        $class = join('\\', $function);

        if (strtolower($class) == 'self' || strtolower($class) == 'static')
        {
            foreach ($this->declarations as $declarations)
            {
                foreach ($declarations as $name => $location)
                {
                    if ($callTID >= $location['firstTID'] && $callTID <= $location['lastTID'])
                    {
                        return $name;
                    }
                }
            }
        }
        else
        {
            $namespace = rtrim($this->detectNamespace($callTID), '\\');
            if (isset($this->namespaces[$namespace ?: '\\']['uses'][$class]))
            {
                return $this->namespaces[$namespace ?: '\\']['uses'][$class];
            }
        }

        return $class;
    }

    /**
     * Get namespace name active at specified token position.
     *
     * @param int $TID
     * @return string
     */
    private function detectNamespace($TID)
    {
        $TID = isset($this->tokens[$TID][self::TOKEN_ID]) ? $this->tokens[$TID][self::TOKEN_ID] : $TID;
        foreach ($this->namespaces as $namespace => $position)
        {
            if ($TID >= $position['firstTID'] && $TID <= $position['lastTID'])
            {
                return $namespace ? $namespace . '\\' : '';
            }
        }

        //Seems like no namespace declaration
        $this->namespaces[''] = [
            'firstTID' => 0,
            'lastTID'  => count($this->tokens),
            'uses'     => []
        ];

        return '';
    }
}