<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Tests\Translator;

use Mockery as m;
use Spiral\Core\HippocampusInterface;
use Spiral\Translator\Catalogue;

class CatalogueTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadLocale()
    {
        $memory = m::mock(HippocampusInterface::class);
        $catalogue = new Catalogue('ru', $memory);

        $this->assertSame('ru', $catalogue->getLocale());
    }

    public function testGetDomainsFromMemory()
    {
        $memory = m::mock(HippocampusInterface::class);
        $catalogue = new Catalogue('ru', $memory);

        $memory->shouldReceive('getSections')->with('translator')->andReturn([
            'ru-messages',
            'ru-views',
            'en-sample'
        ]);

        $this->assertSame(['messages', 'views'], $catalogue->getDomains());
    }

    public function testLoadDomainsFromMemory()
    {
        $memory = m::mock(HippocampusInterface::class);
        $catalogue = new Catalogue('ru', $memory);

        $memory->shouldReceive('getSections')->with('translator')->andReturn([
            'ru-messages',
            'ru-views',
            'en-sample'
        ]);

        $this->assertSame(['messages', 'views'], $catalogue->getDomains());

        $memory->shouldReceive('loadData')->with('ru-messages', 'translator')->andReturn([
            'message' => 'Russian Translation'
        ]);

        $memory->shouldReceive('loadData')->with('ru-views', 'translator')->andReturn([
            'view' => 'Russian View'
        ]);

        $catalogue->loadDomains();
    }

    public function testLoadAndHas()
    {
        $memory = m::mock(HippocampusInterface::class);
        $catalogue = new Catalogue('ru', $memory);

        $memory->shouldReceive('getSections')->with('translator')->andReturn([
            'ru-messages',
            'ru-views',
            'en-sample'
        ]);

        $this->assertSame(['messages', 'views'], $catalogue->getDomains());

        $memory->shouldReceive('loadData')->with('ru-messages', 'translator')->andReturn([
            'message' => 'Russian Translation'
        ]);

        $memory->shouldReceive('loadData')->with('ru-views', 'translator')->andReturn([
            'view' => 'Russian View'
        ]);

        //Invalid domain
        $memory->shouldReceive('loadData')->with('ru-other-domain', 'translator')->andReturn(null);

        $catalogue->loadDomains();

        $this->assertTrue($catalogue->has('messages', 'message'));
        $this->assertTrue($catalogue->has('views', 'view'));

        $this->assertFalse($catalogue->has('messages', 'another-message'));
        $this->assertFalse($catalogue->has('other-domain', 'message'));
    }

    public function testLoadAndGet()
    {
        $memory = m::mock(HippocampusInterface::class);
        $catalogue = new Catalogue('ru', $memory);

        $memory->shouldReceive('getSections')->with('translator')->andReturn([
            'ru-messages',
            'ru-views',
            'en-sample'
        ]);

        $this->assertSame(['messages', 'views'], $catalogue->getDomains());

        $memory->shouldReceive('loadData')->with('ru-messages', 'translator')->andReturn([
            'message' => 'Russian Translation'
        ]);

        $memory->shouldReceive('loadData')->with('ru-views', 'translator')->andReturn([
            'view' => 'Russian View'
        ]);

        $catalogue->loadDomains();

        $this->assertSame('Russian Translation', $catalogue->get('messages', 'message'));
        $this->assertSame('Russian View', $catalogue->get('views', 'view'));
    }

    public function testLoadGetAndSet()
    {
        $memory = m::mock(HippocampusInterface::class);
        $catalogue = new Catalogue('ru', $memory);

        $memory->shouldReceive('getSections')->with('translator')->andReturn([
            'ru-messages',
            'ru-views',
            'en-sample'
        ]);

        $this->assertSame(['messages', 'views'], $catalogue->getDomains());

        $memory->shouldReceive('loadData')->with('ru-messages', 'translator')->andReturn([
            'message' => 'Russian Translation'
        ]);

        $memory->shouldReceive('loadData')->with('ru-views', 'translator')->andReturn([
            'view' => 'Russian View'
        ]);

        $catalogue->loadDomains();

        $this->assertSame('Russian Translation', $catalogue->get('messages', 'message'));
        $this->assertSame('Russian View', $catalogue->get('views', 'view'));

        $this->assertFalse($catalogue->has('views', 'message'));
        $catalogue->set('views', 'message', 'View Message');
        $this->assertTrue($catalogue->has('views', 'message'));

        $this->assertSame('View Message', $catalogue->get('views', 'message'));
    }

    public function testSaveDomains()
    {
        $memory = m::mock(HippocampusInterface::class);
        $catalogue = new Catalogue('ru', $memory);

        $memory->shouldReceive('loadData')->with('ru-test', 'translator')->andReturn([
            'existed' => 'Value'
        ]);

        $memory->shouldReceive('saveData')->with(
            'ru-test',
            [
                'existed' => 'Value',
                'message' => 'Some Test Message'
            ],
            'translator'
        );

        $catalogue->set('test', 'message', 'Some Test Message');
        $catalogue->saveDomains();
    }
}