<?php
/**
 * Dash
 *
 * @link      http://github.com/DASPRiD/Dash For the canonical source repository
 * @copyright 2013 Ben Scholzen 'DASPRiD'
 * @license   http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */

namespace DashTest\Router\Http\Route;

use Dash\Router\Exception\InvalidArgumentException;
use Dash\Router\Exception\RuntimeException;
use Dash\Router\Exception\UnexpectedValueException;
use Dash\Router\Http\MatchResult\MethodNotAllowed;
use Dash\Router\Http\MatchResult\SchemeNotAllowed;
use Dash\Router\Http\MatchResult\SuccessfulMatch;
use Dash\Router\Http\Parser\ParseResult;
use Dash\Router\Http\Parser\ParserInterface;
use Dash\Router\Http\Route\Generic;
use Dash\Router\Http\Route\RouteInterface;
use Dash\Router\Http\RouteCollection\RouteCollection;
use Dash\Router\MatchResult\MatchResultInterface;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Http\Request;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * @covers Dash\Router\Http\Route\Generic
 */
class GenericTest extends TestCase
{
    /**
     * @var Generic
     */
    protected $route;

    /**
     * @var Request
     */
    protected $request;

    public function setUp()
    {
        $this->route = new Generic();

        $this->request = new Request();
        $this->request->setUri('http://example.com/foo/bar');
    }

    public function testNoMatchWithoutConfiguration()
    {
        $this->assertNull($this->route->match($this->request, 0));
    }

    public function testSuccessfulPathMatch()
    {
        $this->route->setPathParser($this->getSuccessfullPathParser());
        $match = $this->route->match($this->request, 4);

        $this->assertInstanceOf(SuccessfulMatch::class, $match);
        $this->assertEquals(['foo' => 'bar'], $match->getParams());
    }

    public function testFailedPathMatch()
    {
        $pathParser = $this->getMock(ParserInterface::class);
        $pathParser
            ->expects($this->once())
            ->method('parse')
            ->with($this->equalTo('/foo/bar'), $this->equalTo(4))
            ->will($this->returnValue(null));

        $this->route->setPathParser($pathParser);
        $this->assertNull($this->route->match($this->request, 4));
    }

    public function testIncompletePathMatch()
    {
        $this->route->setPathParser($this->getIncompletePathParser());
        $this->assertNull($this->route->match($this->request, 4));
    }

    public function testSuccessfulHostnameMatch()
    {
        $this->route->setPathParser($this->getSuccessfullPathParser());
        $this->route->setHostnameParser($this->getSuccessfullHostnameParser());
        $match = $this->route->match($this->request, 4);

        $this->assertInstanceOf(SuccessfulMatch::class, $match);
        $this->assertEquals(['foo' => 'bar', 'baz' => 'bat'], $match->getParams());
    }

    public function testFailedHostnameMatch()
    {
        $hostnameParser = $this->getMock(ParserInterface::class);
        $hostnameParser
            ->expects($this->once())
            ->method('parse')
            ->with($this->equalTo('example.com'), $this->equalTo(0))
            ->will($this->returnValue(null));

        $pathParser = $this->getMock(ParserInterface::class);
        $pathParser
            ->expects($this->never())
            ->method('parse');

        $this->route->setPathParser($pathParser);
        $this->route->setHostnameParser($hostnameParser);
        $this->assertNull($this->route->match($this->request, 4));
    }

    public function testIncompleteHostnameMatch()
    {
        $hostnameParser = $this->getMock(ParserInterface::class);
        $hostnameParser
            ->expects($this->once())
            ->method('parse')
            ->with($this->equalTo('example.com'), $this->equalTo(0))
            ->will($this->returnValue(new ParseResult(['baz' => 'bat'], 7)));

        $pathParser = $this->getMock(ParserInterface::class);
        $pathParser
            ->expects($this->never())
            ->method('parse');

        $this->route->setPathParser($pathParser);
        $this->route->setHostnameParser($hostnameParser);
        $this->assertNull($this->route->match($this->request, 4));
    }

    public function testEarlyReturnOnNonSecureScheme()
    {
        $pathParser = $this->getMock(ParserInterface::class);
        $pathParser
            ->expects($this->never())
            ->method('parse');

        $this->route->setPathParser($pathParser);
        $this->route->setSecure(true);

        $result = $this->route->match($this->request, 0);
        $this->assertInstanceOf(SchemeNotAllowed::class, $result);
        $this->assertEquals('https://example.com/foo/bar', $result->getAllowedUri());
    }

    public function testNoMatchWithEmptyMethod()
    {
        $this->route->setPathParser($this->getSuccessfullPathParser());
        $this->route->setMethods('');

        $result = $this->route->match($this->request, 4);
        $this->assertNull($result);
    }

    public function testMethodNotAllowedResultWithWrongMethod()
    {
        $this->route->setPathParser($this->getSuccessfullPathParser());
        $this->route->setMethods('post');

        $result = $this->route->match($this->request, 4);
        $this->assertInstanceOf(MethodNotAllowed::class, $result);
        $this->assertEquals(['POST'], $result->getAllowedMethods());
    }

    public function testMatchWithWildcardMethod()
    {
        $this->route->setPathParser($this->getSuccessfullPathParser());
        $this->route->setMethods('*');
        $match = $this->route->match($this->request, 4);

        $this->assertInstanceOf(SuccessfulMatch::class, $match);
        $this->assertEquals(['foo' => 'bar'], $match->getParams());
    }

    public function testMatchWithMultipleMethods()
    {
        $this->route->setPathParser($this->getSuccessfullPathParser());
        $this->route->setMethods(['get', 'post']);
        $match = $this->route->match($this->request, 4);

        $this->assertInstanceOf(SuccessfulMatch::class, $match);
        $this->assertEquals(['foo' => 'bar'], $match->getParams());
    }

    public function testMatchOverridesDefaults()
    {
        $this->route->setDefaults(['foo' => 'bat', 'baz' =>'bat']);
        $this->route->setPathParser($this->getSuccessfullPathParser());
        $match = $this->route->match($this->request, 4);

        $this->assertInstanceOf(SuccessfulMatch::class, $match);
        $this->assertEquals(['foo' => 'bar', 'baz' => 'bat'], $match->getParams());
    }

    public function testSetMethodWithInvalidScalar()
    {
        $this->setExpectedException(
            InvalidArgumentException::class,
            '$methods must either be a string or an array'
        );

        $this->route->setMethods(1);
    }

    public function testSetInvalidMethod()
    {
        $this->setExpectedException(
            InvalidArgumentException::class,
            'FOO is not a valid HTTP method'
        );

        $this->route->setMethods('foo');
    }

    public function testIncompletePathMatchWithoutChildMatch()
    {
        $this->route->setChildren($this->getRouteCollection());
        $this->route->setPathParser($this->getIncompletePathParser());
        $this->assertNull($this->route->match($this->request, 4));
    }

    public function testIncompletePathMatchWithChildMatch()
    {
        $this->assignChildren([new SuccessfulMatch(['baz' => 'bat'])]);
        $this->route->setPathParser($this->getIncompletePathParser());
        $match = $this->route->match($this->request, 4);

        $this->assertInstanceOf(SuccessfulMatch::class, $match);
        $this->assertEquals(['foo' => 'bar', 'baz' => 'bat'], $match->getParams());
    }

    public function testUnknownMatchResultTakesPrecedence()
    {
        $expectedMatchResult = $this->getMock(MatchResultInterface::class);
        $this->assignChildren([
            'no-call',
            $expectedMatchResult,
            $this->getMock(MethodNotAllowed::class, [], [], '', false),
            $this->getMock(SchemeNotAllowed::class, [], [], '', false),
            null
        ]);
        $this->route->setPathParser($this->getIncompletePathParser());
        $match = $this->route->match($this->request, 4);

        $this->assertSame($expectedMatchResult, $match);
    }

    public function testFirstSchemeNotAllowedResultIsReturned()
    {
        $expectedMatchResult = $this->getMock(SchemeNotAllowed::class, [], [], '', false);
        $this->assignChildren([
            $this->getMock(SchemeNotAllowed::class, [], [], '', false),
            $expectedMatchResult,
        ]);
        $this->route->setPathParser($this->getIncompletePathParser());
        $match = $this->route->match($this->request, 4);

        $this->assertSame($expectedMatchResult, $match);
    }

    public function testSchemeNotAllowedResultTakesPrecedence()
    {
        $expectedMatchResult = $this->getMock(SchemeNotAllowed::class, [], [], '', false);
        $this->assignChildren([
            $this->getMock(MethodNotAllowed::class, [], [], '', false),
            $expectedMatchResult,
        ]);
        $this->route->setPathParser($this->getIncompletePathParser());
        $match = $this->route->match($this->request, 4);

        $this->assertSame($expectedMatchResult, $match);
    }

    public function testMethodNotAllowedResultsAreMerged()
    {
        $this->assignChildren([new MethodNotAllowed(['GET']), new MethodNotAllowed(['POST'])]);
        $this->route->setPathParser($this->getIncompletePathParser());
        $match = $this->route->match($this->request, 4);

        $this->assertInstanceOf(MethodNotAllowed::class, $match);
        $this->assertEquals(['POST', 'GET'], $match->getAllowedMethods());
    }

    public function testExceptionOnUnexpectedSuccessfulMatchResult()
    {
        $matchResult = $this->getMock(MatchResultInterface::class);
        $matchResult
            ->expects($this->once())
            ->method('isSuccess')
            ->will($this->returnValue(true));

        $this->assignChildren([$matchResult]);
        $this->route->setPathParser($this->getIncompletePathParser());

        $this->setExpectedException(
            UnexpectedValueException::class,
            'Expected instance of Dash\Router\Http\MatchResult\SuccessfulMatch, received'
        );
        $this->route->match($this->request, 4);
    }

    public function testAssembleSecureSchema()
    {
        $this->route->setSecure(true);
        $assemblyResult = $this->route->assemble([]);

        $this->assertEquals('https:', $assemblyResult->generateUri('http', 'example.com', false));
    }

    public function testAssembleHostname()
    {
        $parser = $this->getMock(ParserInterface::class);
        $parser
            ->expects($this->once())
            ->method('compile')
            ->with($this->equalTo(['foo' => 'bar']), $this->equalTo(['baz' => 'bat']))
            ->will($this->returnValue('example.org'));

        $this->route->setHostnameParser($parser);
        $this->route->setDefaults(['baz' => 'bat']);
        $assemblyResult = $this->route->assemble(['foo' => 'bar']);

        $this->assertEquals('//example.org', $assemblyResult->generateUri('http', 'example.com', false));
    }

    public function testAssemblePath()
    {
        $parser = $this->getMock(ParserInterface::class);
        $parser
            ->expects($this->once())
            ->method('compile')
            ->with($this->equalTo(['foo' => 'bar']), $this->equalTo(['baz' => 'bat']))
            ->will($this->returnValue('/bar'));

        $this->route->setPathParser($parser);
        $this->route->setDefaults(['baz' => 'bat']);
        $assemblyResult = $this->route->assemble(['foo' => 'bar']);

        $this->assertEquals('/bar', $assemblyResult->generateUri('http', 'example.com', false));
    }

    public function testAssembleFailsWithoutChildren()
    {
        $this->setExpectedException(RuntimeException::class, 'Route has no children to assemble');
        $this->route->assemble([], 'foo');
    }

    public function testAssemblePassesDownChildName()
    {
        $child = $this->getMock(RouteInterface::class);
        $child
            ->expects($this->once())
            ->method('assemble')
            ->with($this->anything(), $this->equalTo('bar'));

        $routeCollection = $this->getRouteCollection();
        $routeCollection->insert('foo', $child);

        $this->route->setChildren($routeCollection);
        $this->route->assemble([], 'foo/bar');
    }

    public function testAssemblePassesNullWithoutFurtherChildren()
    {
        $child = $this->getMock(RouteInterface::class);
        $child
            ->expects($this->once())
            ->method('assemble')
            ->with($this->anything(), $this->equalTo(null));

        $routeCollection = $this->getRouteCollection();
        $routeCollection->insert('foo', $child);

        $this->route->setChildren($routeCollection);
        $this->route->assemble([], 'foo');
    }

    public function testAssembleIgnoresTrailingSlash()
    {
        $child = $this->getMock(RouteInterface::class);
        $child
            ->expects($this->once())
            ->method('assemble')
            ->with($this->anything(), $this->equalTo(null));

        $routeCollection = $this->getRouteCollection();
        $routeCollection->insert('foo', $child);

        $this->route->setChildren($routeCollection);
        $this->route->assemble([], 'foo/');
    }

    /**
     * @return RouteCollection
     */
    protected function getRouteCollection()
    {
        return new RouteCollection($this->getMock(ServiceLocatorInterface::class));
    }

    /**
     * @return \Dash\Router\Http\Parser\ParserInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getIncompletePathParser()
    {
        $pathParser = $this->getMock(ParserInterface::class);
        $pathParser
            ->expects($this->once())
            ->method('parse')
            ->with($this->equalTo('/foo/bar'), $this->equalTo(4))
            ->will($this->returnValue(new ParseResult(['foo' => 'bar'], 1)));

        return $pathParser;
    }

    /**
     * @return \Dash\Router\Http\Parser\ParserInterface||\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getSuccessfullPathParser()
    {
        $pathParser = $this->getMock(ParserInterface::class);
        $pathParser
            ->expects($this->once())
            ->method('parse')
            ->with($this->equalTo('/foo/bar'), $this->equalTo(4))
            ->will($this->returnValue(new ParseResult(['foo' => 'bar'], 4)));

        return $pathParser;
    }

    /**
     * @return \Dash\Router\Http\Parser\ParserInterface||\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getSuccessfullHostnameParser()
    {
        $hostnameParser = $this->getMock(ParserInterface::class);
        $hostnameParser
            ->expects($this->once())
            ->method('parse')
            ->with($this->equalTo('example.com'), $this->equalTo(0))
            ->will($this->returnValue(new ParseResult(['baz' => 'bat'], 11)));

        return $hostnameParser;
    }

    /**
     * @param array $results
     */
    protected function assignChildren(array $results)
    {
        $routeCollection = $this->getRouteCollection();

        foreach ($results as $index => $result) {
            $childRoute = $this->getMock(RouteInterface::class);

            if ($result === 'no-call') {
                $childRoute
                    ->expects($this->never())
                    ->method('match');
            } else {
                $childRoute
                    ->expects($this->once())
                    ->method('match')
                    ->with($this->equalTo($this->request), $this->equalTo(5))
                    ->will($this->returnValue($result));
            }

            $routeCollection->insert('child' . $index, $childRoute);
        }

        $this->route->setChildren($routeCollection);
    }
}
