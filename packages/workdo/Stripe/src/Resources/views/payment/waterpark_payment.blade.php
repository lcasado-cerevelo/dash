<div class="bg-white rounded-lg shadow p-3">
    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 flex items-center justify-center rounded-full overflow-hidden bg-gray-100">
                <img src="{{ get_module_img('Stripe') }}" alt="" class="w-full h-full object-contain">
            </div>
            <div class="ms-3">
                <label for="stripe-payment" class="cursor-pointer">
                    <h5 class="m-0 capitalize font-medium">{{ Module_Alias_Name('Stripe') }}</h5>
                </label>
            </div>
        </div>
        <div>
            <input
                class="form-radio text-blue-500 cursor-pointer payment_method"
                name="payment_method"
                id="stripe-payment"
                type="radio"
                data-payment-action="{{ route('water.park.pay.with.stripe',[$slug]) }}">
        </div>
    </div>
</div>
