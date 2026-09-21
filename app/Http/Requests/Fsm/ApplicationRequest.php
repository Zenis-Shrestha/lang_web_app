<?php
// Last Modified Date: 10-04-2024
// Developed By: Innovative Solution Pvt. Ltd. (ISPL)    
namespace App\Http\Requests\Fsm;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        if ($this->input('action_type') !== 'confirm') {
            return;
        }

        $scheduleAccept = session('schedule_accept');

        if (!is_array($scheduleAccept)) {
            return;
        }

        $lockedValues = array_filter([
            'road_code' => $scheduleAccept['road_code'] ?? null,
            'bin' => $scheduleAccept['bin'] ?? null,
            'containment_id' => $scheduleAccept['containment_id'] ?? null,
            'ward' => $scheduleAccept['ward'] ?? null,
            'service_provider_id' =>
                $scheduleAccept['service_provider_id'] ?? null,
        ], function ($value) {
            return $value !== null && $value !== '';
        });

        $this->merge($lockedValues);
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
       
        return [
            'road_code' => request()->isMethod('post') ? 'required' : 'nullable',
            'bin' => request()->isMethod('post') ? 'required' : 'nullable',
            'containment_id' => 'required_if:action_type,confirm|nullable|string',
            'action_type' => 'nullable|in:confirm',
            'ward' => 'nullable|integer|min:1',
            'customer_name' => 'required_if:action_type,confirm',
            'customer_gender' => 'required_if:action_type,confirm',
            'customer_contact' => 'nullable|integer',
            'applicant_name' => 'required',
            'applicant_gender' => 'required',
            'applicant_contact' => 'required|integer',
            'containment_code' => '',
            'proposed_emptying_date' => 'required|date|after_or_equal:'.date('m/d/Y'),
            'supervisory_assessment_date' => 'required_if:action_type,confirm|nullable|date|before_or_equal:proposed_emptying_date',
            'service_provider_id' => 'required|integer',
            'landmark' => '',
            'emergency_desludging_status' => 'required_unless:action_type,confirm|nullable|boolean',
            'household_served' => 'nullable|integer|min:1',
            'population_served' => 'nullable|integer|min:1',
            'toilet_count' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Get the error messages to display if validation fails.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'road_code.required' => __('The Street Name/ Street Code is required.'),
            'bin.required' => __('The House Number / BIN is required.'),
            'containment_id.required_if' => __('The Containment ID is required.'),
            'ward.integer' => __('The Ward must be an integer.'),
            'ward.min' => __('The Ward must be at least 1.'),
            'customer_name' => '',
            'customer_gender' => '',
            'customer_contact.integer' => __('The owner contact must be an integer.'),
            'customer_name.required_if' => __('The Owner Name is required.'),
            'customer_gender.required_if' => __('The Owner Gender is required.'),
            'applicant_name.required' => __('The Applicant Name is required.'),
            'applicant_gender.required' => __('The Applicant Gender is required.'),
            'applicant_contact.required' => __('The Applicant Contact (Phone) is required.'),
            'containment_code' => '',
            'proposed_emptying_date.required' => __('The Proposed Emptying Date is required.'),
            'supervisory_assessment_date.required_if' => __('The Supervisory Assessment Date is required.'),
            'supervisory_assessment_date.date' => __('The Supervisory Assessment Date must be a valid date.'),
            'supervisory_assessment_date.before_or_equal' => __('The Supervisory Assessment Date must be on or before the Proposed Emptying Date.'),
            'service_provider_id.required' => __('The Service Provider Name is required.'),
            'landmark' => '',
            'emergency_desludging_status.required' => __('The Emergency Desludging is required.'),
            'emergency_desludging_status.boolean' => __('The Emergency Desludging must be Yes or No.'),
            'household_served.integer' => __('The Household Served must be an integer.'),
            'household_served.min' => __('The Household Served must be at least 1.'),
            'population_served.integer' => __('The Population Served must be an integer.'),
            'population_served.min' => __('The Population Served must be at least 1.'),
            'toilet_count.integer' => __('The Toilet Count must be an integer.'),
            'toilet_count.min' => __('The Toilet Count must be at least 1.'),
            

        ];
    }

}
