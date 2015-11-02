angular.module('app', ['ngRoute', 'appControllers', 'appServices'])

.config(function($routeProvider) {
    $routeProvider
        .when('/', {
            controller: 'MainController',
            templateUrl: 'template/main.html'
        })
        .when('/albums', {
            controller: 'AlbumsController',
            templateUrl: 'template/albums.html'
        })
        .when('/albums/:id', {
            controller: 'AlbumsIdController',
            templateUrl: 'template/albums-id.html'
        })
        .otherwise({
            redirectTo: '/'
        });
})

.run(['$rootScope', '$window', 'fbAuth',
    function($rootScope, $window, fbAuth) {
        $rootScope.user = {};

        /**
         * run as soon as the SDK has completed loading
         */
        $window.fbAsyncInit = function() {
            console.log('fbAsyncInit');

            FB.init({
                appId: '1406945652966023',
                xfbml: true,
                version: 'v2.4'
            });

            fbAuth.watchStatusChange();
        };
    }
]);