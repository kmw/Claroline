<?php

namespace Claroline\CommonBundle\History;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Claroline\CommonBundle\History\Browser;

class BrowserTest extends WebTestCase
{
    /** @var Claroline\CommonBundle\History\Browser */
    private $browser;
    
    /** @var Symfony\Component\HttpFoundation\Request */
    private $request;
    
    /** @var Symfony\Component\HttpFoundation\Session */
    private $session;
    
    public function setUp()
    {
        $this->session = self::createClient()->getContainer()->get('session');
        $this->request = $this->getMockedRequest();
        $this->browser = new Browser($this->request, $this->session, 4);       
    }
    
    public function testBrowserInitsAnArraySessionVariableToHandleHistory()
    {
        $this->assertTrue($this->session->has(Browser::HISTORY_SESSION_VARIABLE));
        $this->assertTrue(is_array($this->session->get(Browser::HISTORY_SESSION_VARIABLE)));
        $this->assertTrue(is_array($this->browser->getContextHistory()));
    }
    
    public function testKeepCurrentContextIsOnlyAllowedForGetRequests()
    {
        $this->setExpectedException('Claroline\CommonBundle\Exception\ClarolineException');
        $browser = new Browser($this->getMockedRequest('POST'), $this->session, 4);
        $browser->keepCurrentContext('Some context name');
    }
    
    public function testKeepCurrentContextRequiresAValidContextName()
    {
        $this->setExpectedException('Claroline\CommonBundle\Exception\ClarolineException');
        $this->browser->keepCurrentContext('');
    }
    
    public function testBrowserBuildsCompleteContextsAndReturnsThemFromTheNewerToTheOlder()
    {
        $browser = new Browser($this->getMockedRequest('GET', 'some/uri/1'), $this->session, 4);
        $browser->keepCurrentContext('A');
        $browser = new Browser($this->getMockedRequest('GET', 'some/uri/2'), $this->session, 4);
        $browser->keepCurrentContext('B');
        $browser = new Browser($this->getMockedRequest('GET', 'some/uri/3'), $this->session, 4);
        $browser->keepCurrentContext('C');
        
        $history = $this->browser->getContextHistory();
       
        $this->assertEquals(3, count($history));
        $this->assertEquals('C', $history[0]->getName());
        $this->assertEquals('B', $history[1]->getName());
        $this->assertEquals('A', $history[2]->getName());      
        $this->assertEquals('some/uri/3', $history[0]->getUri());
        $this->assertEquals('some/uri/2', $history[1]->getUri());
        $this->assertEquals('some/uri/1', $history[2]->getUri());
    }
    
    public function testBrowserKeepsOnlyOneInstanceOfAGivenContext()
    {
        $this->browser->keepCurrentContext('A'); 
        $this->browser->keepCurrentContext('B');
        $this->browser->keepCurrentContext('A');
        $this->browser->keepCurrentContext('C');
            
        $history = $this->browser->getContextHistory();
       
        $this->assertEquals(3, count($history));
        $this->assertEquals('C', $history[0]->getName());
        $this->assertEquals('A', $history[1]->getName());
        $this->assertEquals('B', $history[2]->getName());
    }
    
    public function testBrowserDequeuesOlderElementWhenQueueMaxSizeIsReached()
    {
        $this->browser->keepCurrentContext('A'); 
        $this->browser->keepCurrentContext('B');
        $this->browser->keepCurrentContext('C');
        $this->browser->keepCurrentContext('D');
        $this->browser->keepCurrentContext('E');
        
        $history = $this->browser->getContextHistory();
       
        $this->assertEquals(4, count($history));
        $this->assertEquals('E', $history[0]->getName());
        $this->assertEquals('D', $history[1]->getName());
        $this->assertEquals('C', $history[2]->getName());
        $this->assertEquals('B', $history[3]->getName());
    }
    
    public function testBrowserTruncatesSessionStoredQueueIfSizeConfigParamHasChangedToSmaller()
    {
        $this->browser->keepCurrentContext('A'); 
        $this->browser->keepCurrentContext('B');
        $this->browser->keepCurrentContext('C');
        $this->browser->keepCurrentContext('D');
        
        $otherBrowserInstance = new Browser($this->request, $this->session, 2);
        $history = $otherBrowserInstance->getContextHistory();
        
        $this->assertEquals(2, count($history));
        $this->assertEquals('D', $history[0]->getName());
        $this->assertEquals('C', $history[1]->getName());
    }
    
    public function testGetLastContextReturnsNewerElementInHistoryIfAny()
    {
        $this->browser->keepCurrentContext('A'); 
        $this->browser->keepCurrentContext('B');
        
        $context = $this->browser->getLastContext();
        
        $this->assertEquals('B', $context->getName());
    }
    
    public function testGetLastContextReturnsNullIfNoContextAvailable()
    {
        $this->assertNull($this->browser->getLastContext());
    }
    
    private function getMockedRequest($method = 'GET', $uri = 'some/uri')
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->expects($this->any())
            ->method('getMethod')
            ->will($this->returnValue($method));
        $request->expects($this->any())
            ->method('getUri')
            ->will($this->returnValue($uri));
        
        return $request;
    }
}