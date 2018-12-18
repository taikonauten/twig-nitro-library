<?php

/**
 * This file is part of the Terrific Twig package.
 *
 * (c) Robert Vogt <robert.vogt@namics.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deniaz\Terrific\Twig\Node;

use Deniaz\Terrific\Provider\ContextProviderInterface;
use \Twig_Compiler;
use \Twig_Node;
use \Twig_NodeOutputInterface;
use \Twig_Node_Expression;
use \Twig_Node_Expression_Array;
use \Twig_Node_Expression_Constant;

/**
 * ComponentNode represents a component node.
 *
 * Class ComponentNode
 * @package Deniaz\Terrific\Twig\Node
 */
final class ComponentNode extends Twig_Node implements Twig_NodeOutputInterface
{
    /**
     * @var ContextProviderInterface Context Variable Provider
     */
    private $ctxProvider;

		private $classList;

		private $nodeName;

    /**
     * ComponentNode constructor.
     * @param Twig_Node_Expression $component Expression representing the Component's Identifier.
     * @param ContextProviderInterface $ctxProvider Context Provider.
     * @param Twig_Node_Expression|null $data Expression representing the additional data.
     * @param bool $only Whether a new Child-Context should be created.
     * @param int $lineno Line Number.
     * @param string $tag Tag name associated with the node.
     */
    public function __construct(
        Twig_Node_Expression $component,
        ContextProviderInterface $ctxProvider,
        Twig_Node_Expression $data = null,
        $only = false,
        $lineno,
        $tag = null)
    {

        parent::__construct(
            ['view' => $component, 'data' => $data],
            ['only' => (bool)$only],
            $lineno,
            $tag
        );

        $this->ctxProvider = $ctxProvider;
    }

    /**
     * @param Twig_Compiler $compiler
     */
    public function compile(Twig_Compiler $compiler)
    {
        $compiler->addDebugInfo($this);

        $this->createTerrificContext($compiler);

        $this->addGetTemplate($compiler);

        $this->nodeName = $this->getNode('view')->getAttribute('value');

        $this->classList = $this->buildClassNameArray($this->nodeName, $this->getNode('data'));

        $compiler
            ->raw('->display(array_merge($tContext, array("class_name" => "'. implode(' ', array_merge($this->classList['classes'], $this->classList['modifier'])) .'", "name" => "' . $this->nodeName .'", "classes" => "[' . implode(',',$this->classList['classes']) .']", "modifier" => "[' . implode(',',$this->classList['modifier']) .']")));')
            ->raw("\n\n");


	    $compiler->addDebugInfo($this->getNode('view'));
    }

	/**
	 * Builds class_name as array and returns it
	 * TODO: refactor
	 */
	protected function buildClassNameArray(string $name, $data)
	{

		$classList = ['classes' => [], 'modifier' => []];

    $rawClassesArray = [];
    $rawModifierArray = [];

    if ($data === NULL) {

      return $classList;
    }

		foreach ($data->getKeyValuePairs() as $pair) {

			if ( $pair['key']->getAttribute( 'value' ) === 'classes' ) {

				if ( is_a( $pair['value'], 'Twig_Node_Expression_Constant' ) ) {

					$rawClassesArray[] = $pair['value']->getAttribute( 'value' );

				} else {

					foreach ( $pair['value']->getKeyValuePairs() as $constant ) {

						$rawClassesArray[] = $constant['value']->getAttribute( 'value' );
					}
				}
			}

			if ( $pair['key']->getAttribute( 'value' ) === 'modifier' ) {

				if ( is_a( $pair['value'], 'Twig_Node_Expression_Constant' ) ) {

					$rawModifierArray[] = $name . '--' . $pair['value']->getAttribute( 'value' );

				} else {

					foreach ( $pair['value']->getKeyValuePairs() as $constant ) {

						$rawModifierArray[] = $name . '--' . $constant['value']->getAttribute( 'value' );
					}
				}


			}
		}

		$classList['classes'] = $rawClassesArray;
		$classList['modifier'] = $rawModifierArray;

		return $classList;
	}

    /**
     * @param Twig_Compiler $compiler
     */
    protected function createTerrificContext(Twig_Compiler $compiler)
    {
        $compiler
            ->addIndentation()
            ->raw('$tContext = array_merge($context, array("class_name" => "'. $this->getNode('view')->getAttribute('value') .'", "name" => "'. $this->getNode('view')->getAttribute('value') .'"));')
            ->raw("\n");

        $this->ctxProvider->compile(
            $compiler,
            $this->getNode('view'),
            $this->getNode('data'),
            $this->getAttribute('only')
        );
    }

    /**
     * Adds the first expression (Component Identifier) and compiles the template loading logic.
     * @param Twig_Compiler $compiler
     */
    protected function addGetTemplate(Twig_Compiler $compiler)
    {
        $compiler
            ->write('$this->loadTemplate(')
            ->subcompile($this->getNode('view'))
            ->raw(', ')
            ->repr($compiler->getFilename())
            ->raw(', ')
            ->repr($this->getLine())
            ->raw(')');
    }
}
