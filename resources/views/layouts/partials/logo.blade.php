<div class="row text-center">
	<div class="col-xs-12">
		@if(file_exists(public_path('uploads/logo.png')))
			<img src="/uploads/logo.png" class="img-rounded" alt="Logo" width="150" style="margin-bottom: 30px;">
		@else
			<img src="{{ asset('img/vendify-logo.png') }}" alt="{{ config('app.name') }}"
				style="width: 120px; height: 120px; object-fit: contain; margin-bottom: 20px;">
			<h1 class="text-center page-header" style="color: #fff; font-family: var(--vf-font-heading);">
				{{ config('app.name') }}
			</h1>
		@endif
	</div>
</div>
