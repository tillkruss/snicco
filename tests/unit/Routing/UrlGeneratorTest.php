<?php


    declare(strict_types = 1);


    namespace Tests\unit\Routing;

    use Illuminate\Support\Carbon;
    use Mockery;
    use Snicco\Routing\Route;
    use Snicco\Routing\RouteCollection;
    use Snicco\Support\Str;
    use Snicco\Support\WP;
    use Tests\helpers\CreateDefaultWpApiMocks;
    use Tests\helpers\CreateTestSubjects;
    use Tests\stubs\TestRequest;
    use Tests\UnitTest;

    class UrlGeneratorTest extends UnitTest
    {

        use CreateDefaultWpApiMocks;
        use CreateTestSubjects;

        protected string $app_key = 'base64:m6DiSxvR82mTQOV6G+JcEOV9jjXH1AkHeMfEQ38uxu4=';
        protected RouteCollection $routes;

        protected function beforeTestRun()
        {

            WP::setFacadeContainer($this->container = $this->createContainer());
        }

        protected function beforeTearDown()
        {

            Mockery::close();
            WP::reset();
        }

        private function seeUrl($route_path, $url, bool $secure = true)
        {

            $expected = rtrim(SITE_URL, '/').'/'.ltrim($route_path, '/');

            // Strip https/http since we dont know the scheme of SITE_URL is.
            $expected = Str::after($expected, '://');
            $result = Str::after($url, '://');

            $this->assertSame($expected, $result);

            $scheme = $secure ? 'https://' : 'http://';

            $this->assertStringStartsWith($scheme, $url);


        }

        /** @test */
        public function an_absolute_url_can_be_created_from_a_path()
        {

            $url = $this->newUrlGenerator()->to('foo', [], true, true);

            $this->seeUrl('/foo', $url);

        }

        /** @test */
        public function a_relative_url_can_be_created_from_a_path()
        {

            $generator = $this->newUrlGenerator();

            $url = $generator->to('foo', [], true, false);

            $this->assertSame('/foo', $url);

        }

        /** @test */
        public function the_url_scheme_can_be_set()
        {

            $url = $this->newUrlGenerator()->to('foo', [], true, true);

            $this->seeUrl('/foo', $url, true);

            $url = $this->newUrlGenerator()->to('foo', [], false, true);
            $this->seeUrl('/foo', $url, false);


        }

        /** @test */
        public function query_fragments_can_be_added_to_the_url()
        {

            $url = $this->newUrlGenerator()->to('base', [
                'foo' => 'bar', 'baz' => 'biz', 'boo',
            ], true, true);

            // boo not present due to the numerical index
            $this->seeUrl('/base?foo=bar&baz=biz', $url);

        }

        /** @test */
        public function fragments_can_be_included()
        {

            $url = $this->newUrlGenerator()->to('base#section');
            $this->assertSame('/base#section', $url);

            $url = $this->newUrlGenerator()->to('base#section', ['foo' => 'bar', 'baz' => 'biz']);
            $this->assertSame('/base?foo=bar&baz=biz#section', $url);

        }

        /** @test */
        public function secure_urls_can_be_create_as_an_alias()
        {

            $url = $this->newUrlGenerator()->secure('foo', []);

            $this->seeUrl('/foo', $url);

        }

        /** @test */
        public function query_string_arguments_can_be_added_to_route_urls()
        {

            $g = $this->newUrlGenerator($this->app_key);

            $route = new Route(['GET'], '/foo/{bar}', function () {

                return 'foo';
            });
            $route->name('foo');

            $this->routes->add($route);

            $url = $g->toRoute('foo', ['bar' => 'bar', 'query' => ['name' => 'calvin']]);

            $this->assertSame('/foo/bar?name=calvin', $url);

        }

        /** @test */
        public function trailing_slashes_are_not_added_if_the_url_ends_with_dot_php()
        {

            $g = $this->newUrlGenerator($this->app_key, null, true);

            $path = '/wp-admin/index.php';
            $url = $g->to($path);
            $this->assertSame('/wp-admin/index.php', $url);

            $url = 'https://foo.com/wp-admin/index.php';
            $url = $g->to($url);
            $this->assertSame('/wp-admin/index.php', $url);


        }

        /** @test */
        public function trailing_slashes_are_not_appended_to_the_query_string_for_absolute_urls()
        {

            $g = $this->newUrlGenerator($this->app_key, null, true);

            $path = 'https://foo.com/foo/?page=bar';
            $url = $g->to($path, [], true, true);
            $this->assertSame('https://foo.com/foo/?page=bar', $url);

            $path = 'https://foo.com/foo?page=bar';
            $url = $g->to($path, [], true, true);
            $this->assertSame('https://foo.com/foo/?page=bar', $url);


        }


        /**
         *
         *
         *
         * SIGNED URLS.
         *
         *
         *
         *
         */

        /** @test */
        public function a_signed_url_can_be_created()
        {

            $g = $this->newUrlGenerator($this->app_key);

            $url = $g->signed('foo', 300, true);

            $parts = parse_url($url);
            $query = explode('&', $parts['query']);

            $this->assertSame('https', $parts['scheme']);
            $this->assertStringEndsWith($parts['host'], trim(SITE_URL, '/'));
            $this->assertSame('/foo', $parts['path']);
            $this->assertSame('expires='.Carbon::now()->addSeconds(300)->getTimestamp(), $query[0]);
            $this->assertStringContainsString('signature', $query[1]);


        }

        /** @test */
        public function a_relative_signed_url_can_be_created()
        {

            $g = $this->newUrlGenerator($this->app_key);

            $url = $g->signed('foo', 300, false);

            // Path
            $this->assertStringStartsWith('/foo', $url);

            // expires
            $this->assertStringContainsString(
                'expires='.Carbon::now()->addSeconds(300)->getTimestamp(),
                $url
            );

            // signature
            $this->assertStringContainsString('&signature=', $url);


        }

        /** @test */
        public function the_expiration_time_can_be_set()
        {

            $g = $this->newUrlGenerator($this->app_key);

            $url = $g->signed('foo', 500);

            $parts = parse_url($url);
            $query = explode('&', $parts['query']);

            $this->assertSame('/foo', $parts['path']);
            $this->assertSame('expires='.Carbon::now()->addSeconds(500)->getTimestamp(), $query[0]);
            $this->assertStringContainsString('signature', $query[1]);

        }

        /** @test */
        public function urls_with_the_correct_signature_can_be_validated()
        {

            $g = $this->newUrlGenerator($this->app_key);
            $url = $g->signed('/foo');

            $this->assertTrue($this->magic_link->hasValidSignature(TestRequest::from('GET', $url)));

        }

        /** @test */
        public function a_relative_signed_url_can_be_validated()
        {

            $g = $this->newUrlGenerator($this->app_key);
            $rel_url = $g->signed('/foo', 300);

            // Full url check fails
            $request = TestRequest::fromFullUrl('GET', trim(SITE_URL, '/').'/'.trim($rel_url, '/'));
            $this->assertFalse($this->magic_link->hasValidSignature($request, true));

            // rel url check works
            $this->assertTrue($this->magic_link->hasValidRelativeSignature($request));

        }

        /** @test */
        public function any_modification_to_the_signed_url_will_invalidate_it()
        {

            $g = $this->newUrlGenerator($this->app_key);
            $url = $g->signed('/foo');

            $this->assertTrue($this->magic_link->hasValidSignature(TestRequest::from('GET', $url)));
            $this->assertFalse($this->magic_link->hasValidSignature(TestRequest::from('GET', $url.'a')));

        }

        /** @test */
        public function an_equal_signature_is_invalid_if_its_expired()
        {

            $g = $this->newUrlGenerator($this->app_key);
            $url = $g->signed('/foo', 300);

            $this->assertTrue($this->magic_link->hasValidSignature(TestRequest::fromFullUrl('GET', $url)));

            Carbon::setTestNow(Carbon::now()->addSeconds(301));

            $this->assertFalse($this->magic_link->hasValidSignature(TestRequest::fromFullUrl('GET', $url)));

            Carbon::setTestNow();

        }

        /** @test */
        public function signed_urls_can_be_created_from_routes()
        {

            $g = $this->newUrlGenerator($this->app_key);

            $route = new Route(['GET'], '/foo/{bar}', function () {

                return 'foo';
            });
            $route->name('foo');

            $this->routes->add($route);

            $url = $g->signedRoute('foo', ['bar' => 'bar']);

            $this->assertStringContainsString('?expires='.Carbon::now()->addSeconds(300)
                                                                ->getTimestamp(), $url);
            $this->assertStringContainsString('&signature=', $url);

            $this->assertTrue($this->magic_link->hasValidSignature(TestRequest::fromFullUrl('GET', $url)));
            $this->assertFalse($this->magic_link->hasValidSignature(TestRequest::fromFullUrl('GET', $url.'a')));


        }

        /** @test */
        public function relative_signed_route_urls_can_be_created()
        {

            $g = $this->newUrlGenerator($this->app_key);

            $route = new Route(['GET'], '/foo/{bar}', function () {

                return 'foo';
            });
            $route->name('foo');

            $this->routes->add($route);

            $rel_url = $g->signedRoute('foo', ['bar' => 'bar']);

            $this->assertStringStartsWith('/foo/bar', $rel_url);
            $this->assertStringContainsString('?expires='.Carbon::now()->addSeconds(300)
                                                                ->getTimestamp(), $rel_url);
            $this->assertStringContainsString('&signature=', $rel_url);

            $request = TestRequest::fromFullUrl('GET', SITE_URL.'/'.trim($rel_url, '/'));

            // Full url check fails
            $this->assertFalse($this->magic_link->hasValidSignature($request, true));

            // modified url fails
            $wrong_request = TestRequest::fromFullUrl('GET', SITE_URL.'/'.trim($rel_url.'a', '/'));

            $this->assertFalse($this->magic_link->hasValidRelativeSignature($wrong_request));
            $this->assertTrue($this->magic_link->hasValidRelativeSignature($request));

        }

        /** @test */
        public function signed_urls_can_be_created_with_additional_query_string()
        {

            $g = $this->newUrlGenerator($this->app_key);

            $route = new Route(['GET'], '/foo/{bar}', function () {

                return 'foo';
            });
            $route->name('foo');

            $this->routes->add($route);

            $url = $g->signedRoute('foo', ['bar' => 'bar', 'query' => ['name' => 'calvin']]);

            $this->assertStringContainsString('?expires='.Carbon::now()->addSeconds(300)
                                                                ->getTimestamp(), $url);
            $this->assertStringContainsString('&signature=', $url);
            $this->assertStringContainsString('&name=calvin', $url);

            $this->assertTrue($this->magic_link->hasValidSignature(TestRequest::from('GET', $url)));

            $this->assertFalse($this->magic_link->hasValidSignature(TestRequest::from('GET', $url.'a')));

            $url_with_wrong_query_value = str_replace('name=calvin', 'name=john', $url);

            $this->assertFalse($this->magic_link->hasValidSignature(TestRequest::from('GET', $url_with_wrong_query_value)));

        }

    }
