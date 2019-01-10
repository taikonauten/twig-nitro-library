<?php

namespace Deniaz\Terrific\Twig\Node;

/**
 * This file is part of the Terrific Twig package.
 *
 * (c) Robert Vogt <robert.vogt@namics.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Deniaz\Terrific\Provider\ContextProviderInterface;
use Twig_Compiler;
use Twig_Node;
use Twig_NodeOutputInterface;
use Twig_Node_Expression;
use Twig_Node_Expression_Array;
use Twig_Error;

/**
 * ComponentNode represents a component node.
 *
 * @package Deniaz\Terrific\Twig\Node
 */
class ComponentNode extends Twig_Node implements Twig_NodeOutputInterface {

  /**
   * How the node is called.
   *
   * E.g. 'view'
   * @code
   * {% view 'exampleView' {} %}
   * @endcode
   */
  const NODE_TAG = 'view';

  /**
   * The classes array key of the classes data that is passed to the node.
   */
  const CLASSES_KEY = 'classes';

  /**
   * The modifies array key of the modifies data that is passed to the node.
   */
  const MODIFIERS_KEY = 'modifiers';

  /**
   * Context Variable Provider.
   *
   * @var \Deniaz\Terrific\Provider\ContextProviderInterface
   */
  private $ctxProvider;

  /**
   * The name of the node that is called.
   *
   * @var string
   */
  private $nodeName;

  /**
   * The data passed to the node.
   *
   * @var mixed
   */
  private $nodeData;

  /**
   * ComponentNode constructor.
   *
   * @param \Twig_Node_Expression $component
   *   Expression representing the Component's Identifier.
   * @param \Deniaz\Terrific\Provider\ContextProviderInterface $ctxProvider
   *   Context Provider.
   * @param \Twig_Node_Expression|null $data
   *   Expression representing the additional data.
   * @param bool $only
   *   Whether a new Child-Context should be created.
   * @param int $lineno
   *   Line Number.
   * @param string $tag
   *   Tag name associated with the node.
   */
  public function __construct(
    Twig_Node_Expression $component,
    ContextProviderInterface $ctxProvider,
    Twig_Node_Expression $data = NULL,
    $only = FALSE,
    $lineno = 0,
    $tag = NULL) {

    parent::__construct(
      [self::NODE_TAG => $component, 'data' => $data],
      ['only' => (bool) $only],
      $lineno,
      $tag
    );

    $this->ctxProvider = $ctxProvider;
  }

  /**
   * Compile the node to PHP markup.
   *
   * @param \Twig_Compiler $compiler
   *   The Twig compiler.
   */
  public function compile(Twig_Compiler $compiler) {
    $this->nodeName = $this->getNodeName();
    $this->nodeData = $this->getNode('data');

    $compiler->addDebugInfo($this);

    $this->createTerrificContext($compiler);
    $this->addGetTemplate($compiler);

    $compiler
      ->raw('->display(array_merge($tContext, [');
    $this->compileNameArrayKey($compiler);
    $this->compileClassNameArrayKey($compiler);
    $this->compileClassesArrayKey($compiler);
    $this->compileModifiersArrayKey($compiler);
    $compiler->raw(']));');

    $compiler->addDebugInfo($this->getNode(self::NODE_TAG));
  }

  /**
   * Adds a "name" array key to the compiled output.
   *
   * @param \Twig_Compiler $compiler
   *   The Twig compiler.
   *
   * @return \Twig_Compiler
   *   The Twig compiler.
   */
  private function compileNameArrayKey(Twig_Compiler $compiler) {
    $compiler->raw('"name" => "' . $this->getNodeName() . '",');

    return $compiler;
  }

  /**
   * Adds a "class_name" array key to the compiled output.
   *
   * Contains a string that can be used in a CSS class attribute
   * with the Node name, all classes and modifiers.
   * Modifiers are prefixed with "$nodeName--".
   *
   * @param \Twig_Compiler $compiler
   *   The Twig compiler.
   *
   * @return \Twig_Compiler
   *   The Twig compiler.
   */
  private function compileClassNameArrayKey(Twig_Compiler $compiler) {
    $compiler->raw('"class_name" => "' . $this->getNodeName() . '"." "');
    $this->compileAndImplodeDataElements($compiler, $this->nodeData, self::CLASSES_KEY, ' ', NULL, TRUE);
    $compiler->raw('." "');
    $this->compileAndImplodeDataElements($compiler, $this->nodeData, self::MODIFIERS_KEY, ' ', $this->getNodeName() . '--', TRUE);
    $compiler->raw(',');

    return $compiler;
  }

  /**
   * Adds a CLASSES_KEY array key to the compiled output.
   *
   * Contains an array with all "classes" that were passed to the Node.
   *
   * @param \Twig_Compiler $compiler
   *   The Twig compiler.
   *
   * @return \Twig_Compiler
   *   The Twig compiler.
   */
  private function compileClassesArrayKey(Twig_Compiler $compiler) {
    $compiler->raw('"' . self::CLASSES_KEY . '" => [');
    $this->compileAndImplodeDataElements($compiler, $this->nodeData, self::CLASSES_KEY, ',');
    $compiler->raw('],');

    return $compiler;
  }

  /**
   * Adds a MODIFIERS_KEY array key to the compiled output.
   *
   * Contains an array with all "modifiers" that were passed to the Node.
   *
   * @param \Twig_Compiler $compiler
   *   The Twig compiler.
   *
   * @return \Twig_Compiler
   *   The Twig compiler.
   */
  private function compileModifiersArrayKey(Twig_Compiler $compiler) {
    $compiler->raw('"' . self::MODIFIERS_KEY . '" => [');
    $this->compileAndImplodeDataElements($compiler, $this->nodeData, self::MODIFIERS_KEY, ',', $this->getNodeName() . '--');
    $compiler->raw(']');

    return $compiler;
  }

  /**
   * Compiles data elements that are inside given array key.
   *
   * Glues compiled items together with given $glue.
   *
   * @param \Twig_Compiler $compiler
   *   The Twig compiler.
   * @param array|null $data
   *   The data passed to the Node.
   *   NULL if none is given.
   * @param string $dataKey
   *   Array key by which data to compile should be selected.
   * @param string $glue
   *   What to use as glue between compiled items.
   * @param string|null $compileStringPrefix
   *   If given, this string is prepended to each compiled item.
   * @param bool|null $concatToPrevious
   *   Whether or not, a "." should be added before the first compiled item.
   *
   * @return \Twig_Compiler
   *   The Twig compiler.
   */
  private function compileAndImplodeDataElements(
    Twig_Compiler $compiler,
    $data,
    string $dataKey,
    string $glue,
    string $compileStringPrefix = NULL,
    bool $concatToPrevious = NULL
  ) {
    if ($data !== NULL) {
      $dataPairIndex = 0;
      $keyValuePairs = [];

      if ($data instanceof Twig_Node_Expression_Array) {
        foreach ($data->getKeyValuePairs() as $pair) {
          if ($pair['key']->getAttribute('value') === $dataKey) {
            $keyValuePairs[] = $pair;
          }
        }

        if (!empty($keyValuePairs) && $concatToPrevious) {
          $compiler->raw('.');
        }

        foreach ($keyValuePairs as $pair) {
          if ($compileStringPrefix !== NULL) {
            $compiler->raw('"' . $compileStringPrefix . '".');
          }
          $compiler->subcompile($pair['value']);

          // Is not last item.
          if ($dataPairIndex !== count($data->getKeyValuePairs()) - 1) {
            $compiler->raw($glue);
          }

          $dataPairIndex++;
        }
      }
      else {
        throw new Twig_Error('The arguments passed to node "' . $this->getNodeName() . '" are not valid/supported. A JSON object with key - value mappings must be used.');
      }
    }

    return $compiler;
  }

  /**
   * Adds the Terrific context.
   *
   * @param \Twig_Compiler $compiler
   *   The Twig compiler.
   */
  private function createTerrificContext(Twig_Compiler $compiler) {
    $compiler
      ->addIndentation()
      ->raw('$tContext = $context;');

    $this->ctxProvider->compile(
      $compiler,
      $this->getNode(self::NODE_TAG),
      $this->getNode('data'),
      $this->getAttribute('only')
    );
  }

  /**
   * Adds the first expression (Component Tag).
   *
   * And compiles the template loading logic.
   *
   * @param \Twig_Compiler $compiler
   *   The Twig compiler.
   */
  private function addGetTemplate(Twig_Compiler $compiler) {
    $compiler
      ->write('$this->loadTemplate(')
      ->subcompile($this->getNode(self::NODE_TAG))
      ->raw(', ')
      ->repr($compiler->getFilename())
      ->raw(', ')
      ->repr($this->getLine())
      ->raw(')');
  }

  /**
   * Returns the name of the current node.
   *
   * @return string
   *   The node name.
   */
  private function getNodeName() {
    if (isset($this->nodeName)) {
      return $this->nodeName;
    }
    else {
      return $this->getNode(self::NODE_TAG)->getAttribute('value');
    }
  }

}
