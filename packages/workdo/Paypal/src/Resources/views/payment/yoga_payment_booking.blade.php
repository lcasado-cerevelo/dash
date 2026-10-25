<label
    class="radio-btn shadow-md flex items-center gap-3 p-3 border rounded-md cursor-pointer hover:border-primary transition"
    for="paypal-payment">
    <input type="radio" id="paypal-payment" name="payment_method" class="form-radio text-primary payment_method"
        data-payment-action="{{ route('yoga.courses.payment.with.paypal', $workspace->slug) }}" />
    <img src="{{ get_module_img('Paypal') }}" class="h-6" />
    <span class="text-sm font-medium">{{ Module_Alias_Name('Paypal') }}</span>
</label>