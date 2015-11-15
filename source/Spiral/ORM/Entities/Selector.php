<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM\Entities;

use Psr\Log\LoggerAwareInterface;
use Spiral\Cache\CacheInterface;
use Spiral\Database\Builders\Prototypes\AbstractSelect;
use Spiral\Database\Entities\QueryBuilder;
use Spiral\Database\Entities\QueryCompiler;
use Spiral\Database\Injections\ParameterInterface;
use Spiral\Database\Injections\FragmentInterface;
use Spiral\Database\Query\QueryResult;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\ORM\Entities\Loaders\RootLoader;
use Spiral\ORM\Exceptions\SelectorException;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;
use Spiral\ORM\RecordInterface;

/**
 * Selectors provide QueryBuilder (see Database) like syntax and support for ORM records to be
 * fetched from database. In addition, selection uses set of internal data loaders dedicated to
 * every of record relation and used to pre-load (joins) or post-load (separate query) data for
 * this relations, including additional where conditions and using relation data for parent record
 * filtering queries.
 *
 * Selector loaders may not only be related to SQL databases, but might load data from external
 * sources.
 *
 * @see with()
 * @see load()
 * @see LoaderInterface
 * @see AbstractSelect
 */
class Selector extends AbstractSelect implements LoggerAwareInterface
{
    /**
     * Selector provides set of profiling functionality helps to understand what is going on with
     * query and data parsing.
     */
    use LoggerTrait, BenchmarkTrait;

    /**
     * Class name of record to be loaded.
     *
     * @var string
     */
    protected $class = '';

    /**
     * Data columns are set of columns automatically created by inner loaders using
     * generateColumns() method, this is not the same column set as one provided by user using
     * columns() method. Do not define columns using generateColumns() method outside of loaders.
     *
     * @see generateColumns()
     * @var array
     */
    protected $dataColumns = [];

    /**
     * We have to track count of loader columns to define correct offsets.
     *
     * @var int
     */
    protected $countColumns = 0;

    /**
     * Primary selection loader.
     *
     * @var Loader
     */
    protected $loader = null;

    /**
     * @invisible
     * @var ORM
     */
    protected $orm = null;

    /**
     * @param string $class
     * @param ORM    $orm
     * @param Loader $loader
     */
    public function __construct(ORM $orm, $class, Loader $loader = null)
    {
        $this->class = $class;
        $this->orm = $orm;
        $this->columns = $this->dataColumns = [];

        //We aways need primary loader
        if (empty($this->loader = $loader)) {
            //Selector always need primary data loaded to define data structure and perform query
            //parsing, in most of cases we can easily use RootLoader associated with primary record
            //schema
            $this->loader = new RootLoader(
                $this->orm,
                null,
                $this->orm->getSchema($class)
            );
        }

        //Every ORM loader has ability to declare it's primary database, we are going to use
        //primary loader database to initiate selector
        $database = $this->loader->dbalDatabase();

        //AbstractSelect construction
        parent::__construct(
            $database,
            $database->driver()->queryCompiler($database->getPrefix())
        );
    }

    /**
     * Primary alias points to table related to parent record.
     *
     * @return string
     */
    public function getPrimaryAlias()
    {
        return $this->loader->getAlias();
    }

    /**
     * {@inheritdoc}
     */
    public function columns($columns = ['*'])
    {
        $this->columns = $this->fetchIdentifiers(func_get_args());

        return $this;
    }

    /**
     * Automatically generate set of columns for specified table or alias, method used by loaders
     * in cases where data is joined.
     *
     * @param string $table   Source table name or alias.
     * @param array  $columns Original set of record columns.
     * @return int
     */
    public function generateColumns($table, array $columns)
    {
        $offset = count($this->dataColumns);
        foreach ($columns as $column) {
            $columnAlias = 'c' . (++$this->countColumns);
            $this->dataColumns[] = $table . '.' . $column . ' AS ' . $columnAlias;
        }

        return $offset;
    }

    /**
     * Request primary selector loader to pre-load relation name. Any type of loader can be used
     * for
     * data preloading. ORM loaders by default will select the most efficient way to load related
     * data which might include additional select query or left join. Loaded data will
     * automatically pre-populate record relations. You can specify nested relations using "."
     * separator.
     *
     * Examples:
     *
     * //Select users and load their comments (will cast 2 queries, HAS_MANY comments)
     * User::find()->with('comments');
     *
     * //You can load chain of relations - select user and load their comments and post related to
     * //comment
     * User::find()->with('comments.post');
     *
     * //We can also specify custom where conditions on data loading, let's load only public
     * comments. User::find()->load('comments', [
     *      'where' => ['{@}.status' => 'public']
     * ]);
     *
     * Please note using "{@}" column name, this placeholder is required to prevent collisions and
     * it will be automatically replaced with valid table alias of pre-loaded comments table.
     *
     * //In case where your loaded relation is MANY_TO_MANY you can also specify pivot table
     * conditions,
     * //let's pre-load all approved user tags, we can use same placeholder for pivot table alias
     * User::find()->load('tags', [
     *      'wherePivot' => ['{@}.approved' => true]
     * ]);
     *
     * //In most of cases you don't need to worry about how data was loaded, using external query
     * or
     * //left join, however if you want to change such behaviour you can force load method to
     * INLOAD
     * User::find()->load('tags', [
     *      'method'     => Loader::INLOAD,
     *      'wherePivot' => ['{@}.approved' => true]
     * ]);
     *
     * Attention, you will not be able to correctly paginate in this case and only ORM loaders
     * support different loading types.
     *
     * You can specify multiple loaders using array as first argument.
     *
     * Example:
     * User::find()->load(['posts', 'comments', 'profile']);
     *
     * @see with()
     * @param string $relation
     * @param array  $options
     * @return $this
     */
    public function load($relation, array $options = [])
    {
        if (is_array($relation)) {
            foreach ($relation as $name => $subOption) {
                if (is_string($subOption)) {
                    //Array of relation names
                    $this->load($subOption, $options);
                } else {
                    //Multiple relations or relation with addition load options
                    $this->load($name, $subOption + $options);
                }
            }

            return $this;
        }

        //We are requesting primary loaded to pre-load nested relation
        $this->loader->loader($relation, $options);

        return $this;
    }

    /**
     * With method is very similar to load() one, except it will always include related data to
     * parent query using INNER JOIN, this method can be applied only to ORM loaders and relations
     * using same database as parent record.
     *
     * Method generally used to filter data based on some relation condition.
     * Attention, with() method WILL NOT load relation data, it will only make it accessible in
     * query.
     *
     * By default joined tables will be available in query based on realtion name, you can change
     * joined table alias using relation option "alias".
     *
     * Do not forget to set DISTINCT flag while including HAS_MANY and MANY_TO_MANY relations. In
     * other scenario you will not able to paginate data well.
     *
     * Examples:
     *
     * //Find all users who have comments comments
     * User::find()->with('comments');
     *
     * //Find all users who have approved comments (we can use comments table alias in where
     * statement).
     * User::find()->with('comments')->where('comments.approved', true);
     *
     * //Find all users who have posts which have approved comments
     * User::find()->with('posts.comments')->where('posts_comments.approved', true);
     *
     * //Custom join alias for post comments relation
     * $user->with('posts.comments', [
     *      'alias' => 'comments'
     * ])->where('comments.approved', true);
     *
     * //If you joining MANY_TO_MANY relation you will be able to use pivot table used as relation
     * name
     * //plus "_pivot" postfix. Let's load all users with approved tags.
     * $user->with('tags')->where('tags_pivot.approved', true);
     *
     * //You can also use custom alias for pivot table as well
     * User::find()->with('tags', [
     *      'pivotAlias' => 'tags_connection'
     * ])
     * ->where('tags_connection.approved', false);
     *
     * You can safely combine with() and load() methods.
     *
     * //Load all users with approved comments and pre-load all their comments
     * User::find()->with('comments')->where('comments.approved', true)
     *             ->load('comments');
     *
     * //You can also use custom conditions in this case, let's find all users with approved
     * comments
     * //and pre-load such approved comments
     * User::find()->with('comments')->where('comments.approved', true)
     *             ->load('comments', [
     *                  'where' => ['{@}.approved' => true]
     *              ]);
     *
     * //As you might notice previous construction will create 2 queries, however we can simplify
     * //this construction to use already joined table as source of data for relation via "using"
     * //keyword
     * User::find()->with('comments')->where('comments.approved', true)
     *             ->load('comments', ['using' => 'comments']);
     *
     * //You will get only one query with INNER JOIN, to better understand this example let's use
     * //custom alias for comments in with() method.
     * User::find()->with('comments', ['alias' => 'commentsR'])->where('commentsR.approved', true)
     *             ->load('comments', ['using' => 'commentsR']);
     *
     * @see load()
     * @param string $relation
     * @param array  $options
     * @return $this
     */
    public function with($relation, array $options = [])
    {
        if (is_array($relation)) {
            foreach ($relation as $name => $options) {
                if (is_string($options)) {
                    //Array of relation names
                    $this->with($options, []);
                } else {
                    //Multiple relations or relation with addition load options
                    $this->with($name, $options);
                }
            }

            return $this;
        }

        //Requesting primary loader to join nested relation, will only work for ORM loaders
        $this->loader->joiner($relation, $options);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(QueryCompiler $compiler = null)
    {
        //We have to reset aliases if we own this compiler
        $compiler = !empty($compiler) ? $compiler : $this->compiler->reset();

        //Primary loader may add custom conditions to select query
        $this->loader->configureSelector($this);

        if (empty($columns = $this->columns)) {
            //If no user columns were specified we are going to use columns defined by our loaders
            //in addition it will return RecordIterator instance as result instead of QueryResult
            $columns = !empty($this->dataColumns) ? $this->dataColumns : ['*'];
        }

        return $compiler->compileSelect(
            [$this->loader->getTable() . ' AS ' . $this->loader->getAlias()],
            $this->distinct,
            $columns,
            $this->joinTokens,
            $this->whereTokens,
            $this->havingTokens,
            $this->grouping,
            $this->ordering,
            $this->limit,
            $this->offset
        );
    }

    /**
     * {@inheritdoc}
     *
     * Return type will depend if custom columns set were used.
     *
     * @param array $callbacks Callbacks to be used in record iterator as magic methods.
     * @return QueryResult|RecordIterator
     */
    public function getIterator(array $callbacks = [])
    {
        if (!empty($this->columns) || !empty($this->grouping)) {
            //QueryResult for user requests
            return $this->run();
        }

        return new RecordIterator($this->orm, $this->class, $this->fetchData(), true, $callbacks);
    }

    /**
     * All records.
     *
     * @return RecordInterface[]
     */
    public function all()
    {
        return $this->getIterator()->all();
    }

    /**
     * Execute query and every related query to compile records data in tree form - every relation
     * data will be included as sub key.
     *
     * Attention, Selector will cache compiled data tree and not query itself to keep data integrity
     * and to skip data compilation on second query.
     *
     * @return array
     */
    public function fetchData()
    {
        //Pagination!
        $this->applyPagination();
        $statement = $this->sqlStatement();

        if (!empty($this->cacheLifetime)) {
            $cacheKey = $this->cacheKey ?: md5(serialize([$statement, $this->getParameters()]));

            if (empty($this->cacheStore)) {
                $this->cacheStore = $this->orm->container()->get(CacheInterface::class)->store();
            }

            if ($this->cacheStore->has($cacheKey)) {
                $this->logger()->debug("Selector result were fetched from cache.");

                //We are going to store parsed result, not queries
                return $this->cacheStore->get($cacheKey);
            }
        }

        //We are bypassing run() method here to prevent query caching, we will prefer to cache
        //parsed data rather that database response
        $result = $this->database->query($statement, $this->getParameters());

        //In many cases (too many inloads, too complex queries) parsing can take significant amount
        //of time, so we better profile it
        $benchmark = $this->benchmark('parseResult', $statement);

        //Here we are feeding selected data to our primary loaded to parse it and and create
        //data tree for our records
        $this->loader->parseResult($result, $rowsCount);

        $this->benchmark($benchmark);

        //Memory freeing
        $result->close();

        //This must force loader to execute all post loaders (including ODM and etc)
        $this->loader->loadData();

        //Now we can request our primary loader for compiled data
        $data = $this->loader->getResult();

        //Memory free! Attention, it will not reset columns aliases but only make possible to run
        //query again
        $this->loader->clean();

        if (!empty($this->cacheLifetime) && !empty($cacheKey)) {
            //We are caching full records tree, not queries
            $this->cacheStore->set($cacheKey, $data, $this->cacheLifetime);
        }

        return $data;
    }

    /**
     * Fetch one record from database using it's primary key. You can use INLOAD and JOIN_ONLY
     * loaders with HAS_MANY or MANY_TO_MANY relations with this method as no limit were used.
     *
     * @see findOne()
     * @param mixed $id Primary key value.
     * @return RecordEntity|null
     * @throws SelectorException
     */
    public function findByPK($id)
    {
        $primaryKey = $this->loader->getPrimaryKey();

        if (empty($primaryKey)) {
            throw new SelectorException(
                "Unable to fetch data by primary key, no primary key found."
            );
        }

        //No limit here
        return $this->findOne([$primaryKey => $id], false);
    }

    /**
     * Fetch one record from database. Attention, LIMIT statement will be used, meaning you can not
     * use loaders for HAS_MANY or MANY_TO_MANY relations with data inload (joins), use default
     * loading method.
     *
     * @see findByPK()
     * @param array $where    Selection WHERE statement.
     * @param bool  $setLimit Use limit 1.
     * @return RecordEntity|null
     */
    public function findOne(array $where = [], $setLimit = true)
    {
        if (!empty($where)) {
            $this->where($where);
        }

        $data = $this->limit($setLimit ? 1 : null)->fetchData();
        if (empty($data)) {
            return null;
        }

        return $this->orm->record($this->class, $data[0]);
    }

    /**
     * Update all matched records with provided columns set. You are no allowed to use join
     * conditions or with() method, you can update your records manually in cases like that.
     *
     * @param array $update Array of columns to be updated, compatible with UpdateQuery.
     * @return int
     * @throws SelectorException
     */
    public function update(array $update)
    {
        if (!empty($this->havingTokens)) {
            throw new SelectorException(
                "Unable to build UPDATE statement using select, HAVING statement not supported."
            );
        }

        if (!empty($this->joinTokens)) {
            throw new SelectorException(
                "Unable to build UPDATE statement using select, JOINS statement not supported."
            );
        }

        $statement = $this->updateStatement($update);

        $normalized = [];
        foreach ($update as $value) {
            if ($value instanceof QueryBuilder) {
                foreach ($value->getParameters() as $parameter) {
                    $normalized[] = $parameter;
                }

                continue;
            }

            if ($value instanceof FragmentInterface && !$value instanceof ParameterInterface) {
                continue;
            }

            $normalized[] = $value;
        }

        return $this->database->execute($statement, $this->compiler->orderParameters(
            QueryCompiler::UPDATE_QUERY,
            $this->whereParameters,
            $this->onParameters,
            [],
            $normalized
        ));
    }


    /**
     * Delete all matched records and return count of affected rows. You are no allowed to use join
     * conditions or with() method, you can delete your records manually in cases like that.
     *
     * @return int
     * @throws SelectorException
     */
    public function delete()
    {
        if (!empty($this->havingTokens)) {
            throw new SelectorException(
                "Unable to build DELETE statement using select, HAVING statement not supported."
            );
        }

        if (!empty($this->joinTokens)) {
            throw new SelectorException(
                "Unable to build DELETE statement using select, JOINS statement not supported."
            );
        }

        return $this->database->execute($this->deleteStatement(),
            $this->compiler->orderParameters(
                QueryCompiler::DELETE_QUERY,
                $this->whereParameters,
                $this->onParameters
            ));
    }

    /**
     * Create update statement based on WHERE statement and columns set provided by Selector.
     *
     * @param array         $columns
     * @param QueryCompiler $compiler
     * @return string
     */
    protected function updateStatement(array $columns, QueryCompiler $compiler = null)
    {
        $compiler = !empty($compiler) ? $compiler : $this->compiler->reset();
        $this->loader->configureSelector($this, false);

        return $compiler->compileUpdate(
            $this->loader->getTable() . ' AS ' . $this->loader->getAlias(),
            $columns,
            $this->whereTokens
        );
    }

    /**
     * Create delete statement based on WHERE statement provided by Selector.
     *
     * @param QueryCompiler $compiler
     * @return string
     */
    protected function deleteStatement(QueryCompiler $compiler = null)
    {
        $compiler = !empty($compiler) ? $compiler : $this->compiler->reset();
        $this->loader->configureSelector($this, false);

        return $compiler->compileDelete(
            $this->loader->getTable() . ' AS ' . $this->loader->getAlias(),
            $this->whereTokens
        );
    }
}