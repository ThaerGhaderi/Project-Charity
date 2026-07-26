<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
        'title'        => 'sometimes|string|max:255',
        'description'  => 'sometimes|string|nullable',
        'goal_amount'  => 'sometimes|numeric|min:1',
        'category'     => 'sometimes|nullable|in:إطعام,مساجد,تعليم,صحة,مياه,أيتام',
        'status'       => 'sometimes|in:نشطة,مغلقة,مكتملة,ملغية,متوقفة',
        'is_emergency' => 'sometimes|boolean',
        'start_date'   => 'sometimes|date',
        'end_date'     => 'sometimes|date|after:start_date',
        
        ];
    }
}
