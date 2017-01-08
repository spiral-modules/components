<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities;

use Spiral\Core\Component;
use Spiral\ORM\Entities\Loaders\RootLoader;
use Spiral\ORM\ORMInterface;

/**
 * Attention, RecordSelector DOES NOT extends QueryBuilder but mocks it!
 */
class RecordSelector extends Component
{
    /**
     * @var string
     */
    private $class;

    /**
     * @invisible
     * @var ORMInterface
     */
    private $orm;

    /**
     * @var RootLoader
     */
    public $loader;

    /**
     * @param string       $class
     * @param ORMInterface $orm
     */
    public function __construct(string $class, ORMInterface $orm)
    {
        $this->class = $class;
        $this->orm = $orm;

        $this->loader = new RootLoader(
            $class,
            $orm->define($class, ORMInterface::R_SCHEMA),
            $orm
        );
    }

    /**
     * Get associated class.
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
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
     *
     * @param string|array $relation
     * @param array        $options
     *
     * @return $this|self
     */
    public function load($relation, array $options = []): self
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
        $this->loader->loadRelation($relation, $options);

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
     * By default joined tables will be available in query based on relation name, you can change
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
     * User::find()->with('comments')
     *             ->where('comments.approved', true)
     *             ->load('comments', ['using' => 'comments']);
     *
     * //You will get only one query with INNER JOIN, to better understand this example let's use
     * //custom alias for comments in with() method.
     * User::find()->with('comments', ['alias' => 'commentsR'])
     *             ->where('commentsR.approved', true)
     *             ->load('comments', ['using' => 'commentsR']);
     *
     * @see load()
     *
     * @param string|array $relation
     * @param array        $options
     *
     * @return $this|self
     */
    public function with($relation, array $options = []): self
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
        $this->loader->loadRelation($relation, $options, true);

        return $this;
    }

    public function fetchData(): array
    {
        $node = $this->loader->createNode();

        //Working with parser defined by loader itself
        $this->loader->loadData($node);

        return $node->getResult();
    }

    /**
     * @return \Interop\Container\ContainerInterface|null
     */
    protected function iocContainer()
    {
        if ($this->orm instanceof Component) {
            //Working inside ORM container scope
            return $this->orm->iocContainer();
        }

        return parent::iocContainer();
    }
}