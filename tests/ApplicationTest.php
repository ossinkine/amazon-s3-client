<?php

use Aws\Common\Result;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Silex\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\Routing\Generator\UrlGenerator;

class ApplicationTest extends WebTestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var UrlGenerator
     */
    protected $urlGenerator;

    /**
     * @var MockObject
     */
    protected $s3ClientMock;

    public function setUp()
    {
        parent::setUp();
        $this->client = $this->createClient();
        $this->urlGenerator = $this->app['url_generator'];
    }

    public function testLogin()
    {
        $loginUrl = $this->urlGenerator->generate('login');
        $crawler = $this->client->request('GET', $loginUrl);
        $this->assertTrue($this->client->getResponse()->isOk());
        $form = $crawler->filter('#form-signin')->form();
        $form['key'] = 'foo';
        $form['secret'] = 'bar';
        $this->client->submit($form);
        // setup cookie and redirect to referer
        $this->assertTrue($this->client->getResponse()->isRedirect('http://localhost'.$loginUrl));
        $cookies = $this->client->getResponse()->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertEquals($this->app['amazon_s3_credentials_cookie_name'], $cookies[0]->getName());
        $this->assertEquals(
            json_encode(array_map(function ($field) { return $field->getValue(); }, $form->all())),
            $cookies[0]->getValue()
        );
        // redirect to homepage
        $this->client->followRedirect();
        $this->assertTrue($this->client->getResponse()->isRedirect($this->urlGenerator->generate('list')));
    }

    public function testFollowLogin()
    {
        $listUrl = $this->urlGenerator->generate('list', ['bucket' => 'foo']);
        $crawler = $this->client->request('GET', $listUrl);
        $this->assertTrue($this->client->getResponse()->isOk());
        $form = $crawler->filter('#form-signin')->form();
        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isRedirect('http://localhost'.$listUrl));
    }

    public function testLogout()
    {
        $this->authorize();
        $listUrl = $this->urlGenerator->generate('list', ['bucket' => 'foo']);
        $this->s3ClientMock->expects($this->once())->method('listBuckets')->willReturn(new Result([]));
        $this->s3ClientMock->expects($this->once())->method('listObjects')->willReturn(new Result([]));
        $crawler = $this->client->request('GET', $listUrl);
        $this->assertTrue($this->client->getResponse()->isOk());
        $form = $crawler->filter('#form-logout')->form();
        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isRedirect('http://localhost'.$listUrl));
        $cookies = $this->client->getResponse()->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertEquals($this->app['amazon_s3_credentials_cookie_name'], $cookies[0]->getName());
        $this->assertNull($cookies[0]->getValue());
    }

    public function testUnauthorizedList()
    {
        $listUrl = $this->urlGenerator->generate('list', ['bucket' => 'foo']);
        $crawler = $this->client->request('GET', $listUrl);
        $this->assertTrue($this->client->getResponse()->isOk());
        $this->assertCount(1, $crawler->filter('#form-signin'));
    }

    public function testList()
    {
        $this->authorize();
        $listUrl = $this->urlGenerator->generate('list');
        $this->s3ClientMock->expects($this->once())->method('listBuckets')->willReturn(new Result([]));
        $this->client->request('GET', $listUrl);
        $this->assertTrue($this->client->getResponse()->isOk());
    }

    public function testListBucket()
    {
        $this->authorize();
        $listUrl = $this->urlGenerator->generate('list', ['bucket' => 'foo']);
        $listBuckets = new Result([
            'Buckets' => [
                [
                    'Name'         => 'foo',
                    'CreationDate' => '2014-08-01T14:00:00.000Z',
                ],
                [
                    'Name'         => 'bar',
                    'CreationDate' => '2014-07-31T20:30:40.000Z',
                ],
            ],
        ]);
        $listObjects = new Result([
            'Contents' => [
                [
                    'Key'          => 'baz',
                    'ETag'         => '"'.md5('baz').'"',
                    'LastModified' => '2014-09-10T11:12:13.000Z',
                    'Size'         => 1024,
                ],
                [
                    'Key'          => 'qux',
                    'ETag'         => '"'.md5('qux').'"',
                    'LastModified' => '2014-09-11T21:22:23.000Z',
                    'Size'         => 2048,
                ],
                [
                    'Key'          => 'quxx',
                    'ETag'         => '"'.md5('quxx').'"',
                    'LastModified' => '2014-09-12T00:00:00.000Z',
                    'Size'         => 512,
                ],
            ],
            'IsTruncated' => false,
        ]);
        $this->s3ClientMock->expects($this->once())->method('listBuckets')->willReturn($listBuckets);
        $this->s3ClientMock->expects($this->once())->method('listObjects')->willReturn($listObjects);
        $crawler = $this->client->request('GET', $listUrl);
        $this->assertTrue($this->client->getResponse()->isOk());
        // buckets
        $list = $crawler->filter('#list-bucket li');
        $active = $list->filter('.active');
        $this->assertCount(count($listBuckets['Buckets']), $list);
        $this->assertCount(1, $active);
        $this->assertEquals($list->eq(0), $active);
        $urlGenerator = $this->urlGenerator;
        $list->each(function (Crawler $bucket, $index) use ($urlGenerator, $listBuckets) {
            $link = $bucket->filter('a');
            $expectedName = $listBuckets['Buckets'][$index]['Name'];
            $this->assertEquals($expectedName, $link->text());
            $this->assertEquals($urlGenerator->generate('list', ['bucket' => $expectedName]), $link->attr('href'));
        });
        // objects
        $list = $crawler->filter('#list-object tbody tr');
        $this->assertCount(count($listObjects['Contents']), $list);
        $list->each(function (Crawler $object, $index) use ($listObjects) {
            $link = $object->filter('td')->eq(0)->filter('a');
            $expectedKey = $listObjects['Contents'][$index]['Key'];
            $this->assertEquals($expectedKey, $link->text());
            $this->assertEquals('http://foo.s3.amazonaws.com/'.$expectedKey, $link->attr('href'));
            $this->assertEquals($listObjects['Contents'][$index]['Size'], $object->filter('td')->eq(1)->text());
        });
    }

    public function createApplication()
    {
        $app = new Application();
        $app['amazon_s3_client'] = $this->s3ClientMock = $this->getMock(
            'Aws\S3\S3Client',
            ['listBuckets', 'listObjects'],
            [],
            '',
            false
        );

        return $app;
    }

    protected function authorize()
    {
        $cookieName = $this->app['amazon_s3_credentials_cookie_name'];
        $this->client->getCookieJar()->set(
            new Cookie(
                $cookieName,
                json_encode(['key' => 'foo', 'secret' => 'bar'])
            )
        );
    }
}
