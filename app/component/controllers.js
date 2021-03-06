angular.module('appControllers', [])

.controller('MainController', function($scope) {

})

.controller('AlbumsController', function($scope, appHttp) {
	$scope.nodeId = '';
	$scope.items = [];

	$scope.getItems = function() {
		if ($scope.isLoading) {
			return;
		}

		$scope.isLoading = true;
		$scope.items = [];

		var params = {
			nodeId: $scope.nodeId
		};

		var onSuccess = function(res) {
			if (!res || res.error) {
				return console.warn('getAlbums', res.error.message);
			}

			$scope.items = res.data;
		};

		var onError = function() {
			console.warn('getAlbums', arguments);
		};

		var onFinally = function() {
			$scope.isLoading = false;
		};

		appHttp.post('getAlbums', params).success(onSuccess).error(onError).finally(onFinally);
	};
})

.controller('AlbumsIdController', function($scope, $routeParams, $timeout, appHttp) {
	$scope.nodeId = $routeParams.id;
	$scope.items = [];
	$scope.paging = {
		cursors: {}
	};

	$scope.totalSaved = 0;

	$scope.getItems = function(paging) {
		if ($scope.isLoading) {
			return;
		}

		$scope.items = [];
		$scope.isLoading = true;

		$scope.totalSaved = 0;

		var params = {
			nodeId: $scope.nodeId,
			limit: 100
		};

		if (paging && paging == 'next' && $scope.paging.cursors.after) {
			params.after = $scope.paging.cursors.after;
		}

		if (paging && paging == 'previous' && $scope.paging.cursors.before) {
			params.before = $scope.paging.cursors.before;
		}

		var onSuccess = function(res) {
			if (!res || res.error) {
				return console.warn('getPhotos', res.error.message);
			}

			$scope.items = res.data;
			$scope.paging = res.paging;
			
			$scope.totalSaved = $scope.getTotalSaved();

			if ($scope.auto) {
				$scope.saveAll();
			}
		};

		var onError = function() {
			console.warn('getPhotos', arguments);
		};

		var onFinally = function() {
			$scope.isLoading = false;
		};

		appHttp.post('getPhotos', params).success(onSuccess).error(onError).finally(onFinally);
	};

	$scope.saveAll = function() {
		for (var i = 0; i < $scope.items.length; i++) {
			$scope.saveItem(i);
		};
	};

	$scope.saveItem = function($index) {
		var item = $scope.items[$index];

		if (item.isProcessing) {
			return;
		}

		item.isProcessing = true;
		
		var params = {
			id: item.id
		};

		var onSuccess = function(res) {
			if (!res || res.error) {
				return console.warn('savePhoto', res.error.message);
			}

			angular.extend(item, res.data);
			afterSuccess();
		};

		var afterSuccess = function() {
			$scope.totalSaved = $scope.getTotalSaved();

			if ($scope.totalSaved == $scope.items.length) {
				if ($scope.auto && $scope.paging.next) {
					$timeout(function() {
						$scope.getItems('next');
					});
				}
			}
		};

		var onError = function() {
			console.warn('savePhoto', arguments);
		};

		var onFinally = function() {
			item.isProcessing = false;
		};

		if (item.is_saved && item.file_exist) {
			afterSuccess();
			onFinally();
			return;
		}

		appHttp.post('savePhoto', params).success(onSuccess).error(onError).finally(onFinally);
	};

	$scope.getTotalSaved = function() {
		var total = 0;

		for (var i = 0; i < $scope.items.length; i++) {
			if ($scope.items[i].is_saved && $scope.items[i].file_exist) {
				total++;
			}
		};

		return total;
	};
});