<!-- Last Modified Date: 18-04-2024
Developed By: Innovative Solution Pvt. Ltd. (ISPL)   -->
<div class="card-body">
        <div class="form-group row required">
            {!! Form::label('company_name', __('Company Name'), ['class' => 'col-sm-3 control-label']) !!}
            <div class="col-sm-3">
                {!! Form::text('company_name', null, ['class' => 'form-control', 'placeholder' => __('Company Name')]) !!}
            </div>
        </div>

        <div class="form-group row required">
            {!! Form::label('email', __('Email'), ['class' => 'col-sm-3 control-label']) !!}
            <div class="col-sm-3">
                {!! Form::text('email', null, ['class' => 'form-control', 'placeholder' => __('Email')]) !!}
            </div>
        </div>

        <div class="form-group row required">
            {!! Form::label('ward', __('Ward Number'), ['class' => 'col-sm-3 control-label']) !!}
            <div class="col-sm-3">
                {!! Form::select('ward', $wards, null, ['class' => 'form-control', 'placeholder' => __('Ward Number')]) !!}
            </div>
        </div>

        <div class="form-group row required">
            {!! Form::label('company_location', __('Address'), ['class' => 'col-sm-3 control-label']) !!}
            <div class="col-sm-3">
                {!! Form::text('company_location', null, ['class' => 'form-control', 'placeholder' => __('Address')]) !!}
            </div>
        </div>

        <div class="form-group row required">
            {!! Form::label('contact_person', __('Contact Person Name'), ['class' => 'col-sm-3 control-label']) !!}
            <div class="col-sm-3">
                {!! Form::text('contact_person', null, ['class' => 'form-control', 'placeholder' => __('Contact Person Name')]) !!}
            </div>
        </div>

        <div class="form-group row required">
            {!! Form::label('contact_gender', __('Contact Person Gender'), ['class' => 'col-sm-3 control-label']) !!}
            <div class="col-sm-3">
                {!! Form::select('contact_gender', ['Male' => 'Male', 'Female' => 'Female', 'Others' => 'Others'], null, ['class' => 'form-control', 'placeholder' => __('Contact Person Gender')]) !!}
            </div>
        </div>

        <div class="form-group row required">
            {!! Form::label('contact_number', __('Contact Person Number'), ['class' => 'col-sm-3 control-label']) !!}
            <div class="col-sm-3">
                {!! Form::text('contact_number', null, ['class' => 'form-control', 'placeholder' => __('Contact Person Number'), 'oninput' => "validateOwnerContactInput(this)"]) !!}
            </div>
        </div>
        {{-- Contract Document --}}
        <div class="form-group row required">
            {!! Form::label(
                'contract_document_pdf',
                __('Contract Document'),
                ['class' => 'col-sm-3 control-label']
            ) !!}

            <div class="col-sm-3">
                @if(
                    !empty($serviceProvider) &&
                    !empty($serviceProvider->contract_document_pdf)
                )
                    <div class="mb-2">
                        <a
                            href="{{ asset('storage/' . $serviceProvider->contract_document_pdf) }}"
                            target="_blank"
                            class="btn btn-info btn-sm"
                        >
                            <i class="fa fa-file-pdf-o"></i>
                            {{ __('View Current PDF') }}
                        </a>
                    </div>
                @elseif(!empty($serviceProvider))
                    <div class="text-muted mb-2">
                        {{ __('No contract document has been uploaded.') }}
                    </div>
                @endif

                {!! Form::file('contract_document_pdf', [
                    'class' => 'form-control',
                    'accept' => 'application/pdf'
                ]) !!}
            </div>
        </div>

        {{-- Service Area --}}
        <div class="form-group row required">
            <div class="col-sm-3 d-flex align-items-center">
                {!! Form::label(
                    'service_area',
                    __('Service Area (Wards)'),
                    ['class' => 'control-label mb-0']
                ) !!}

                @if(!$serviceProvider)
                    <button
                        type="button"
                        id="uncovered-service-areas-info"
                        class="btn btn-link btn-sm p-0 ml-2 text-info"
                        aria-label="{{ __('View uncovered service areas') }}"
                        aria-haspopup="true"
                        data-toggle="popover"
                    >
                        <i
                            class="fa fa-info-circle"
                            aria-hidden="true"
                        ></i>
                    </button>
                @endif
            </div>

            <div class="col-sm-3">
                {!! Form::select(
                    'service_area[]',
                    $wards,
                    old('service_area', $selectedWards ?? []),
                    [
                        'id' => 'service_area',
                        'class' => 'form-control select2',
                        'multiple' => true
                    ]
                ) !!}
            </div>
        </div>

        @if(!$serviceProvider)
            <div
                id="uncovered-service-areas-popover-content"
                class="d-none"
            >
                @if(empty($uncoveredWards))
                    <p class="mb-0 text-success">
                        <i
                            class="fa fa-check-circle"
                            aria-hidden="true"
                        ></i>
                        {{ __('All service areas are currently covered.') }}
                    </p>
                @else
                    <p class="mb-2">
                        {{ __('These wards currently have no operational service provider.') }}
                    </p>

                    <div>
                        @foreach($uncoveredWards as $uncoveredWard)
                            <span class="badge badge-light border mr-1 mb-1">
                                {{ __('Ward') }} {{ $uncoveredWard }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <div class="form-group row required">
            {!! Form::label('status', __('Status'), ['class' => 'col-sm-3 control-label']) !!}
            <div class="col-sm-3">
                {!! Form::select('status', $serviceProviderStatus, null, ['class' => 'form-control chosen-select', 'placeholder' => __('Status')]) !!}
            </div>
        </div>


	@if(!$serviceProvider)
	<div class="form-group row">
		{!! Form::label('create_user', __('Create User?'),['class' => 'col-sm-3 control-label']) !!}
		<div class="col-sm-3">
		<input type="checkbox" 
       name="create_user" 
       id="create_user" 
       class="create_user" 
       value="on" 
       {{ old('create_user') ? 'checked' : '' }}>
       

		</div>
	</div>
	<div id="user-password">
	<div class="form-group row ">
    {!! Form::label('password',  __('Password'), ['class' => 'col-sm-3 control-label']) !!}
    <div class="col-sm-3">
        <!-- Password Input -->
        <input type="password" 
               class="form-control" 
               name="password" 
               id="password" 
               placeholder="{{ __('Password')}}">

        <!-- Error/Requirement Message Container -->
        <div id="password-error" class="mt-1" style="display: none; color: red;">
            <ul style="margin-bottom: 0; padding-left: 1rem;">
                <li id="char-count">{{__('The Password must be at least 8 characters.')}}</li>
                <li id="uppercase-lowercase">{{__('The Password must contain at least one uppercase and one lowercase letter.')}}</li>
                <li id="symbol">{{__('The Password must contain at least one symbol.')}}</li>
                <li id="number">{{__('The Password must contain at least one number.')}}</li>
            </ul>
        </div>
    </div>
</div>
<div class="form-group row">
    {!! Form::label('password_confirmation',  __('Confirm Password'), ['class' => 'col-sm-3 control-label']) !!}
    <div class="col-sm-3">
        <input type="password" 
               class="form-control" 
               name="password_confirmation" 
               id="password_confirmation" 
               placeholder="{{ __('Confirm Password')}}">

        <!-- Confirm Password Requirements -->
        <div id="confirm-password-error" class="mt-1" style="display: none; color: red;">
            <ul style="margin-bottom: 0; padding-left: 1rem;">
                <li id="confirm-char-count">{{__('The Password must be at least 8 characters.')}}</li>
                <li id="confirm-uppercase-lowercase">{{__('The Password must contain at least one uppercase and one lowercase letter.')}}</li>
                <li id="confirm-symbol">{{__('The Password must contain at least one symbol.')}}</li>
                <li id="confirm-number">{{__('The Password must contain at least one number.')}}</li>
                <li id="confirm-match">{{__('Passwords must match.')}}</li>
            </ul>
        </div>
    </div>
</div>
</div>
	@endif
</div><!-- /.box-body -->
<div class="card-footer">
	<a href="{{ action('Fsm\ServiceProviderController@index') }}" class="btn btn-info">{{ __('Back to List')}}</a>
	{!! Form::submit( __('Save'), ['class' => 'btn btn-info']) !!}
</div><!-- /.box-footer -->


@push('scripts')
    <script>
        $(function () {
            var serviceArea = $('#service_area');

            serviceArea.select2({
                placeholder: @json(__('Select Service Area Wards')),
                closeOnSelect: false,
                allowClear: true,
                width: '100%'
            });

            serviceArea
                .val(@json(old('service_area', $selectedWards ?? [])))
                .trigger('change');

            var uncoveredServiceAreasInfo =
                $('#uncovered-service-areas-info');

            if (
                uncoveredServiceAreasInfo.length &&
                $.fn.popover
            ) {
                uncoveredServiceAreasInfo.popover({
                    title: @json(__('Uncovered service areas')),
                    content: function () {
                        return $(
                            '#uncovered-service-areas-popover-content'
                        ).html();
                    },
                    html: true,
                    trigger: 'focus',
                    placement: 'auto',
                    container: 'body'
                });
            }
        });
    </script>
@endpush
