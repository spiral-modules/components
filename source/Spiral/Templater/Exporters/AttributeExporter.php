<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Templater\Exporters;

use Spiral\Templater\AbstractExporter;

/**
 * Export user defined (outer) blocks as tag attributes.
 *
 * Use following pattern: node:attributes[="condition"]
 */
class AttributeExporter extends AbstractExporter
{
    /**
     * {@inheritdoc}
     */
    public function mountBlocks()
    {
        if (preg_match_all('/ node:attributes(?:=\"([^\'"]+)\")?/i', $this->content, $matches)) {
            //We have to sort from longest to shortest
            uasort($matches[0], function ($replaceA, $replaceB) {
                return strlen($replaceB) - strlen($replaceA);
            });

            foreach ($matches[0] as $id => $replace) {
                $blocks = [];

                //That's why we need longest first (prefix mode)
                foreach ($this->getBlocks($matches[1][$id]) as $name => $value) {
                    if ($value === null) {
                        $blocks[$name] = $name;
                        continue;
                    }

                    $blocks[$name] = $name . '="' . $value . '"';
                }

                //Injecting
                $this->content = str_replace(
                    $replace,
                    $blocks ? ' ' . join(' ', $blocks) : '',
                    $this->content
                );
            }
        }

        return $this->content;
    }
}