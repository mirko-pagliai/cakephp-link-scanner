<?php
declare(strict_types=1);

/**
 * This file is part of cakephp-link-scanner.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright   Copyright (c) Mirko Pagliai
 * @link        https://github.com/mirko-pagliai/cakephp-link-scanner
 * @license     https://opensource.org/licenses/mit-license.php MIT License
 */
namespace LinkScanner\Test\TestCase;

use LinkScanner\Entity;
use LinkScanner\TestSuite\TestCase;

/**
 * EntityTest class
 */
class EntityTest extends TestCase
{
    /**
     * @var \LinkScanner\Entity|(\LinkScanner\Entity&\PHPUnit\Framework\MockObject\MockObject)
     */
    protected Entity $Entity;

    /**
     * @var int
     */
    protected static int $currentErrorLevel;

    /**
     * @inheritDoc
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$currentErrorLevel = error_reporting(E_ALL & ~E_USER_DEPRECATED);
    }

    /**
     * @inheritDoc
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        error_reporting(self::$currentErrorLevel);
    }

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->Entity ??= $this->getMockForAbstractClass(Entity::class, [['code' => 200]]);
    }

    /**
     * @uses \LinkScanner\Entity::has()
     * @test
     */
    public function testHas(): void
    {
        $this->assertTrue($this->Entity->has('code'));
        $this->assertFalse($this->Entity->has('noExisting'));

        //`has()` method with empty, `null` and `false` values returns `true`
        $this->assertTrue($this->Entity->set('keyWithEmptyValue', '')->has('keyWithEmptyValue'));
        $this->assertTrue($this->Entity->set('keyWithFalse', false)->has('keyWithFalse'));
    }

    /**
     * @uses \LinkScanner\Entity::hasValue()
     * @test
     */
    public function testHasValue(): void
    {
        $this->assertTrue($this->Entity->hasValue('code'));
        $this->assertFalse($this->Entity->hasValue('noExisting'));

        //`hasValue()` method with empty, `null` and `false` values return `false`
        $this->assertFalse($this->Entity->set('keyWithEmptyValue', '')->hasValue('keyWithEmptyValue'));
        $this->assertFalse($this->Entity->set('keyWithFalse', false)->hasValue('keyWithFalse'));
    }

    /**
     * @uses \LinkScanner\Entity::__get()
     * @uses \LinkScanner\Entity::get()
     * @test
     */
    public function testGet(): void
    {
        $this->assertSame(200, $this->Entity->code);
        $this->assertSame(200, $this->Entity->get('code'));

        $this->assertNull($this->Entity->noExisting);
        $this->assertNull($this->Entity->get('noExisting'));
        $this->assertSame('default', $this->Entity->get('noExisting', 'default'));
    }

    /**
     * @uses \LinkScanner\Entity::isEmpty()
     * @test
     */
    public function testIsEmpty(): void
    {
        $this->assertFalse($this->Entity->isEmpty('code'));
        $this->assertTrue($this->Entity->isEmpty('noExisting'));

        //`isEmpty()` method with empty, `null` and `false` values return `true`
        $this->assertTrue($this->Entity->set('keyWithEmptyValue', '')->isEmpty('keyWithEmptyValue'));
        $this->assertTrue($this->Entity->set('keyWithFalse', false)->isEmpty('keyWithFalse'));
    }

    /**
     * @uses \LinkScanner\Entity::set()
     * @test
     */
    public function testSet(): void
    {
        $result = $this->Entity->set('newKey', 'newValue');
        $this->assertInstanceOf(Entity::class, $result);
        $this->assertSame('newValue', $this->Entity->get('newKey'));

        $this->Entity->set(['alfa' => 'first', 'beta' => 'second']);
        $this->assertSame('first', $this->Entity->get('alfa'));
        $this->assertSame('second', $this->Entity->get('beta'));

        $this->assertSame('', $this->Entity->set('keyWithEmptyValue', '')->get('keyWithEmptyValue'));
    }

    /**
     * @uses \LinkScanner\Entity::toArray()
     * @test
     */
    public function testToArray(): void
    {
        $expected = ['code' => 200, 'newKey' => 'newValue'];
        $result = $this->Entity->set('newKey', 'newValue')->toArray();
        $this->assertSame($expected, $result);

        $expected += ['subEntity' => ['subKey' => 'subValue']];

        $subEntity = $this->getMockForAbstractClass(Entity::class, [['subKey' => 'subValue']]);
        $result = $this->Entity->set(compact('subEntity'))->toArray();
        $this->assertSame($expected, $result);
    }

    /**
     * @uses \LinkScanner\Entity::offsetExists()
     * @uses \LinkScanner\Entity::offsetGet()
     * @uses \LinkScanner\Entity::offsetSet()
     * @uses \LinkScanner\Entity::offsetUnset()
     * @test
     */
    public function testArrayAccess(): void
    {
        $this->Entity['newKey'] = 'a key';
        $this->assertTrue(isset($this->Entity['newKey']));
        $this->assertSame('a key', $this->Entity['newKey']);
        unset($this->Entity['newKey']);
        $this->assertFalse(isset($this->Entity['newKey']));
    }
}
