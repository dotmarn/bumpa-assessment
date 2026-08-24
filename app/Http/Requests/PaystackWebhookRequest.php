<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PaystackWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $secret = (string) config('services.paystack.secret_key');
        $signature = $this->header('x-paystack-signature');

        return $secret !== ''
            && is_string($signature)
            && hash_equals(hash_hmac('sha512', $this->getContent(), $secret), $signature);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string'],
            'data' => ['required', 'array'],
        ];
    }
}
