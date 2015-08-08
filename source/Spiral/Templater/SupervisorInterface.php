<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Templater;

/**
 * SupervisorInterface used by Node to define html syntax for control elements and create valid
 * behaviour for html constructions.
 *
 * @see  BehaviourInterface
 * @see  ExtendsBehaviourInterface
 * @todo add links to behaviours
 */
interface SupervisorInterface
{
    /**
     * In strict mode every unpaired close tag or other html error will raise an StrictModeException.
     *
     * @return bool
     */
    static function isStrictMode();

    /**
     * Define html tag behaviour based on supervisor syntax settings.
     *
     * @param array $token
     * @param array $content
     * @param Node  $node
     * @return mixed|BehaviourInterface
     */
    public function getBehaviour(array $token, array $content, Node $node);
}