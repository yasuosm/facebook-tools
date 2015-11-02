angular.module('appServices', [])

.factory('appHttp', ['$rootScope', '$http', function($rootScope, $http) {
    var appHttp = {};

    appHttp.post = function(name, params, config) {
        var url = '../include/api.php/' + name;
        return $http.post(url, params, config);
    };

    return appHttp;
}])

.factory('fbAuth', ['$rootScope', '$http', function($rootScope, $http) {
    var fbAuth = {
        authResponse: {}
    };

    fbAuth.watchStatusChange = function() {
        var _self = this;
        FB.Event.subscribe('auth.authResponseChange', function(res) {
            console.log('auth.authResponseChange', res);

            if (res.status === 'connected') {
                _self.onLogin(res.authResponse);
            } else {
                _self.onLogout();
            }
        });
    };

    fbAuth.onLogin = function(authResponse) {
        $rootScope.$apply(function() {
            $rootScope.authResponse = this.authResponse = authResponse;
            $http.defaults.headers.common.token = authResponse.accessToken;
        });
    };

    fbAuth.onLogout = function() {
        $rootScope.$apply(function() {
            $rootScope.authResponse = this.authResponse = {};
            $http.defaults.headers.common.token = '';
        });
    };

    return fbAuth;
}]);