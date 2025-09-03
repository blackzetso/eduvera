<!DOCTYPE html>
<html lang="en">

	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, shrink-to-fit=9">
		<meta name="description" content="Gambolthemes">
		<meta name="author" content="Gambolthemes">
		<title>Eduvera - Admin Dashboard</title>

		<!-- Favicon Icon -->
		<link rel="icon" type="image/png" href="{{ asset('admin/theme1/images/fav.png') }}">

		<!-- Stylesheets -->
		<link href='http://fonts.googleapis.com/css?family=Roboto:400,700,500' rel='stylesheet'>
		<link href='{{ asset('admin/theme1/vendor/unicons-2.0.1/css/unicons.css') }}' rel='stylesheet'>
		<link href="{{ asset('admin/theme1/css/vertical-responsive-menu1.min.css') }}" rel="stylesheet">
		<link href="{{ asset('admin/theme1/css/instructor-dashboard.css') }}" rel="stylesheet">
		<link href="{{ asset('admin/theme1/css/instructor-responsive.css') }}" rel="stylesheet">
		<link href="{{ asset('admin/theme1/css/night-mode.css') }}" rel="stylesheet">

		<!-- Vendor Stylesheets -->
		<link href="{{ asset('admin/theme1/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
		<link href="{{ asset('admin/theme1/vendor/OwlCarousel/assets/owl.carousel.css') }}" rel="stylesheet">
		<link href="{{ asset('admin/theme1/vendor/OwlCarousel/assets/owl.theme.default.min.css') }}" rel="stylesheet">
		<link href="{{ asset('admin/theme1/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
		<link href="{{ asset('admin/theme1/vendor/bootstrap-select/docs/docs/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">
		<link rel="stylesheet" type="text/css" href="{{ asset('admin/theme1/vendor/semantic/semantic.min.css') }}">

	</head>

<body>
	<!-- Header Start -->
	@include('admin.theme1.layout.navbar')
	<!-- Header End -->
	<!-- Left Sidebar Start -->
	@include('admin.theme1.layout.sidebar')
	<!-- Left Sidebar End -->
	<!-- Body Start -->
	@yield('content')
	<!-- Body End -->

	<script src="{{ asset('admin/theme1/js/vertical-responsive-menu.min.js') }}"></script>
	<script src="{{ asset('admin/theme1/js/jquery-3.7.1.min.js') }}"></script>
	<script src="{{ asset('admin/theme1/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
	<script src="{{ asset('admin/theme1/vendor/OwlCarousel/owl.carousel.js') }}"></script>
	<script src="{{ asset('admin/theme1/vendor/bootstrap-select/docs/docs/dist/js/bootstrap-select.js') }}"></script>
	<script src="{{ asset('admin/theme1/vendor/semantic/semantic.min.js') }}"></script>
	<script src="{{ asset('admin/theme1/js/custom1.js') }}"></script>
	<script src="{{ asset('admin/theme1/js/night-mode.js') }}"></script>

</body>
</html>
