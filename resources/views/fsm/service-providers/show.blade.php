<!-- Last Modified Date: 18-04-2024
Developed By: Innovative Solution Pvt. Ltd. (ISPL)   -->
@extends('layouts.dashboard')
@section('title', $page_title)
@section('content')
<div class="card card-info">
	<div class="card-header bg-transparent">
		<a href="{{ action('Fsm\ServiceProviderController@index') }}" class="btn btn-info">{{__('Back to List')}}</a>
		{{-- <a href="{{ action('Fsm\ServiceProviderController@create') }}" class="btn btn-info">Create new Service Provider</a> --}}
	</div><!-- /.card-header -->
	<div class="form-horizontal">
		<div class="card-body">
		<div class="form-group row">
    {!! Form::label('company_name', __('Company Name'), ['class' => 'col-sm-3 control-label']) !!}
    <div class="col-sm-3">
        {!! Form::label(null, $serviceProvider->company_name, ['class' => 'form-control']) !!}
    </div>
		</div>

		<div class="form-group row">
			{!! Form::label('email', __('Email'), ['class' => 'col-sm-3 control-label']) !!}
			<div class="col-sm-3">
				{!! Form::label(null, $serviceProvider->email, ['class' => 'form-control']) !!}
			</div>
		</div>

		<div class="form-group row">
			{!! Form::label('ward', __('Ward Number'), ['class' => 'col-sm-3 control-label']) !!}
			<div class="col-sm-3">
				{!! Form::label(null, $serviceProvider->ward, ['class' => 'form-control']) !!}
			</div>
		</div>

		<div class="form-group row">
			{!! Form::label('company_location', __('Address'), ['class' => 'col-sm-3 control-label']) !!}
			<div class="col-sm-3">
				{!! Form::label(null, $serviceProvider->company_location, ['class' => 'form-control']) !!}
			</div>
		</div>

		<div class="form-group row">
			{!! Form::label('contact_person', __('Contact Person Name'), ['class' => 'col-sm-3 control-label']) !!}
			<div class="col-sm-3">
				{!! Form::label(null, $serviceProvider->contact_person, ['class' => 'form-control']) !!}
			</div>
		</div>

		<div class="form-group row">
			{!! Form::label('contact_gender', __('Contact Person Gender'), ['class' => 'col-sm-3 control-label']) !!}
			<div class="col-sm-3">
				{!! Form::label(null, $serviceProvider->contact_gender, ['class' => 'form-control']) !!}
			</div>
		</div>

		<div class="form-group row">
			{!! Form::label('contact_number', __('Contact Person Number'), ['class' => 'col-sm-3 control-label']) !!}
			<div class="col-sm-3">
				{!! Form::label(null, $serviceProvider->contact_number, ['class' => 'form-control']) !!}
			</div>
		</div>

		<div class="form-group row">
			{!! Form::label('contract_document_pdf', __('Contract Document'), ['class' => 'col-sm-3 control-label']) !!}
			<div class="col-sm-3">
				@if(!empty($serviceProvider->contract_document_pdf))
					<a href="{{ asset('storage/' . $serviceProvider->contract_document_pdf) }}"
						target="_blank"
						rel="noopener noreferrer"
						class="btn btn-info btn-sm">
						<i class="fa fa-file-pdf-o"></i> {{ __('View Contract Document') }}
					</a>
				@else
					<div class="form-control">{{ __('Not available') }}</div>
				@endif
			</div>
		</div>

		<div class="form-group row">
			{!! Form::label('service_area', __('Service Area (Wards)'), ['class' => 'col-sm-3 control-label']) !!}
			<div class="col-sm-3">
				<div class="form-control">
					{{ !empty($serviceProvider->service_area) ? str_replace(',', ', ', $serviceProvider->service_area) : __('Not available') }}
				</div>
			</div>
		</div>

		<div class="form-group row">
			{!! Form::label('status', __('Status'), ['class' => 'col-sm-3 control-label']) !!}
			<div class="col-sm-3">
				{!! Form::label(null, $status, ['class' => 'form-control']) !!}
			</div>
		</div>


		</div><!-- /.box-body -->
	</div>
</div><!-- /.box -->
@stop

