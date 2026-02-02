<?php

use PHPUnit\Framework\Attributes\Depends;
use Rize\UriTemplate;
use Rize\UriTemplate\Node;
use Rize\UriTemplate\Operator;
use Rize\UriTemplate\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    protected function service()
    {
        return new Parser();
    }

    public function testParseTemplate()
    {
        $input = 'http://www.example.com/{term:1}/{term}/{test*}/foo{?query,number}';
        $expected = [new Node\Literal('http://www.example.com/'), new Node\Expression(
            'term:1',
            Operator\Abstraction::createById(''),
            [new Node\Variable(
                'term:1',
                ['modifier' => ':', 'value'    => 1],
            )],
        ), new Node\Literal('/'), new Node\Expression(
            'term',
            Operator\Abstraction::createById(''),
            [new Node\Variable(
                'term',
                ['modifier' => null, 'value'    => null],
            )],
        ), new Node\Literal('/'), new Node\Expression(
            'test*',
            Operator\Abstraction::createById(''),
            [new Node\Variable(
                'test',
                ['modifier' => '*', 'value'    => null],
            )],
        ), new Node\Literal('/foo'), new Node\Expression(
            'query,number',
            Operator\Abstraction::createById('?'),
            [new Node\Variable(
                'query',
                ['modifier' => null, 'value'    => null],
            ), new Node\Variable(
                'number',
                ['modifier' => null, 'value'    => null],
            )],
        )];

        $service = $this->service();
        $actual  = $service->parse($input);

        $this->assertEquals($expected, $actual);
    }

    public function testParseTemplateWithLiteral()
    {
        // will pass
        $uri = new UriTemplate('http://www.example.com/v1/company/', []);
        $params = $uri->extract('/{countryCode}/{registrationNumber}/test{.format}', '/gb/0123456/test.json');
        static::assertEquals(['countryCode' => 'gb', 'registrationNumber' => '0123456', 'format' => 'json'], $params);
    }

    #[Depends('testParseTemplateWithLiteral')]
    public function testParseTemplateWithTwoVariablesAndDotBetween()
    {
        // will fail
        $uri = new UriTemplate('http://www.example.com/v1/company/', []);
        $params = $uri->extract('/{countryCode}/{registrationNumber}{.format}', '/gb/0123456.json');
        static::assertEquals(['countryCode' => 'gb', 'registrationNumber' => '0123456', 'format' => 'json'], $params);
    }

    #[Depends('testParseTemplateWithLiteral')]
    public function testParseTemplateWithTwoVariablesAndDotBetweenStrict()
    {
        // will fail
        $uri = new UriTemplate('http://www.example.com/v1/company/', []);
        $params = $uri->extract('/{countryCode}/{registrationNumber}{.format}', '/gb/0123456.json', true);
        static::assertEquals(['countryCode' => 'gb', 'registrationNumber' => '0123456', 'format' => 'json'], $params);
    }

    #[Depends('testParseTemplateWithLiteral')]
    public function testParseTemplateWithThreeVariablesAndDotBetweenStrict()
    {
        // will fail
        $uri = new UriTemplate('http://www.example.com/v1/company/', []);
        $params = $uri->extract('/{countryCode}/{registrationNumber}{.namespace}{.format}', '/gb/0123456.company.json');
        static::assertEquals(['countryCode' => 'gb', 'registrationNumber' => '0123456', 'namespace' => 'company', 'format' => 'json'], $params);
    }
}
