<?php

declare(strict_types=1);

namespace Laminas\Router\Http;

use Laminas\Router\Exception;
use Laminas\Router\Exception\InvalidArgumentException;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\RequestInterface as Request;
use Traversable;

use function array_merge;
use function is_array;
use function is_int;
use function is_numeric;
use function method_exists;
use function preg_match;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;

/**
 * Regex route.
 */
class Regex implements RouteInterface
{
    /**
     * Regex to match.
     *
     * @var string
     */
    protected $regex;

    /**
     * Default values.
     *
     * @var array
     */
    protected $defaults;

    /**
     * Specification for URL assembly.
     *
     * Parameters accepting substitutions should be denoted as "%key%"
     *
     * @var string
     */
    protected $spec;

    /**
     * List of assembled parameters.
     *
     * @var array
     */
    protected $assembledParams = [];

    /**
     * @internal
     * @deprecated Since 3.9.0 This property will be removed or made private in version 4.0
     *
     * @var int|null
     */
    public $priority;

    /**
     * Create a new regex route.
     *
     * @param  string $regex
     * @param  string $spec
     * @param  array  $defaults
     */
    public function __construct($regex, $spec, array $defaults = [])
    {
        $this->regex    = $regex;
        $this->spec     = $spec;
        $this->defaults = $defaults;
    }

    /**
     * factory(): defined by RouteInterface interface.
     *
     * @see    \Laminas\Router\RouteInterface::factory()
     *
     * @param  array|Traversable $options
     * @return Regex
     * @throws InvalidArgumentException
     */
    public static function factory($options = [])
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable set of options',
                __METHOD__
            ));
        }

        if (! isset($options['regex'])) {
            throw new Exception\InvalidArgumentException('Missing "regex" in options array');
        }

        if (! isset($options['spec'])) {
            throw new Exception\InvalidArgumentException('Missing "spec" in options array');
        }

        if (! isset($options['defaults'])) {
            $options['defaults'] = [];
        }

        return new static($options['regex'], $options['spec'], $options['defaults']);
    }

    /**
     * match(): defined by RouteInterface interface.
     *
     * @param  int $pathOffset
     * @return RouteMatch|null
     */
    public function match(Request $request, $pathOffset = null)
    {
        if (! method_exists($request, 'getUri')) {
            return;
        }

        $uri  = $request->getUri();
        $path = $uri->getPath();

        if ($pathOffset !== null) {
            $result = preg_match('(\G' . $this->regex . ')', $path, $matches, 0, $pathOffset);
        } else {
            $result = preg_match('(^' . $this->regex . '$)', $path, $matches);
        }

        if (! $result) {
            return;
        }

        $matchedLength = strlen($matches[0]);

        foreach ($matches as $key => $value) {
            if (is_numeric($key) || is_int($key) || $value === '') {
                unset($matches[$key]);
            } else {
                $matches[$key] = rawurldecode($value);
            }
        }

        return new RouteMatch(array_merge($this->defaults, $matches), $matchedLength);
    }

    /**
     * assemble(): Defined by RouteInterface interface.
     *
     * @see    \Laminas\Router\RouteInterface::assemble()
     *
     * @param  array $params
     * @param  array $options
     * @return mixed
     */
    public function assemble(array $params = [], array $options = [])
    {
        $url                   = $this->spec;
        $mergedParams          = array_merge($this->defaults, $params);
        $this->assembledParams = [];

        foreach ($mergedParams as $key => $value) {
            $spec = '%' . $key . '%';

            if (strpos($url, $spec) !== false) {
                $url = str_replace($spec, rawurlencode((string) $value), $url);

                $this->assembledParams[] = $key;
            }
        }

        return $url;
    }

    /**
     * getAssembledParams(): defined by RouteInterface interface.
     *
     * @see    RouteInterface::getAssembledParams
     *
     * @return array
     */
    public function getAssembledParams()
    {
        return $this->assembledParams;
    }
}
