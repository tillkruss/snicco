<?php


    declare(strict_types = 1);


    /** @var Router $router */
    use WPEmerge\Routing\Router;
    use WPEmerge\ExceptionHandling\Exceptions\HttpException;
    use WPEmerge\Session\Exceptions\InvalidCsrfTokenException;

    $router->get('error/500', function () {

        throw new HttpException(500, 'Something went wrong here.');

    });

    $router->get('error/400', function () {

        throw new HttpException(400, 'Bad Request.');

    });

    $router->get('error/419', function () {

        throw new InvalidCsrfTokenException();

    });

