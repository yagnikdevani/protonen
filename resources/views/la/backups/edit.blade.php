@extends("la.layouts.app")

@section("contentheader_title")
	<a href="{{ url(config('laraadmin.adminRoute') . '/backups') }}">Backup</a> :
@endsection
@section("contentheader_description", $backup->$view_col)
@section("section", "Backups")
@section("section_url", url(config('laraadmin.adminRoute') . '/backups'))
@section("sub_section", "Edit")

@section("htmlheader_title", "Backups Edit : ".$backup->$view_col)

@section("main-content")

@if (count($errors) > 0)
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="box">
	<div class="box-header">
		
	</div>
	<div class="box-body">
		<div class="row">
			<div class="col-md-8 col-md-offset-2">
				{!! Form::model($backup, ['route' => [config('laraadmin.adminRoute') . '.backups.update', $backup->id ], 'method'=>'PUT', 'id' => 'backup-edit-form']) !!}
					@la_form($module)
					
					{{--
					@la_input($module, 'name')
					@la_input($module, 'file_name')
					@la_input($module, 'backup_size')
					--}}
                    <br>
					<div class="form-group">
						{!! Form::submit( 'Update', ['class'=>'btn btn-success']) !!} <button class="btn btn-default pull-right"><a href="{{ url(config('laraadmin.adminRoute') . '/backups') }}">Cancel</a></button>
					</div>
				{!! Form::close() !!}
			</div>
		</div>
	</div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
	$("#backup-edit-form").validate({
		
	});
});
</script>
@endpush
