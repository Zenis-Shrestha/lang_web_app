<!-- Last Modified Date: 18-04-2024
Developed By: Innovative Solution Pvt. Ltd. (ISPL)   -->
@extends('layouts.dashboard')
@section('title', $page_title)
@section('content')
@include('layouts.components.error-list')
@include('layouts.components.success-alert')
@include('layouts.components.error-alert')
<div class="card card-info">
	{!! Form::open(['url' => 'fsm/service-providers', 'class' => 'form-horizontal', 'files' => true]) !!}
		@include('fsm/service-providers.partial-form', ['submitButtomText' => __('Save')])
	{!! Form::close() !!}
</div><!-- /.box -->
@stop
