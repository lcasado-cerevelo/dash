<div class="bg-white rounded-lg shadow p-3">
    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 flex items-center justify-center rounded-full overflow-hidden bg-gray-100">
                <img src="{{ get_module_img('Paypal') }}" alt="" class="w-full h-full object-contain">
            </div>
            <div class="ms-3">
                <label for="paypal-payment" class="cursor-pointer">
                    <h5 class="m-0 capitalize font-medium">{{ Module_Alias_Name('Paypal') }}</h5>
                </label>
            </div>
        </div>
        <div>
            <input
                type="radio"
                name="payment_method"
                id="paypal-payment"
                class="form-radio text-blue-500 cursor-pointer payment_method"
                data-payment-action="{{ route('water.park.pay.with.paypal', [$slug]) }}">
        </div>
    </div>
</div>
