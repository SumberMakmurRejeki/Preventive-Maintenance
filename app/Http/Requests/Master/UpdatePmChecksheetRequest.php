<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;

class UpdatePmChecksheetRequest extends StorePmChecksheetRequest
{
    protected $redirectRoute = 'master-checksheet.edit';

    public function rules(): array
    {
        $checksheetId = (int) $this->route('id');
        $rules = parent::rules();

        $rules['checksheet_code'] = [
            'required',
            'string',
            'max:50',
            Rule::unique('pm_checksheets', 'checksheet_code')->ignore($checksheetId),
        ];

        return $rules;
    }
}
