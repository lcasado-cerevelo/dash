<label
    class="flex items-center gap-3 p-3 border rounded-md cursor-pointer hover:border-primary transition">
    <input type="radio" name="payment_method" id="stripe-payment" class="form-radio text-primary payment_method" data-payment-action="{{ route('invest.deposit.pay.payment.with.stripe',[$slug]) }}" />
    <img src="{{ get_module_img('Stripe') }}" class="h-6" />
    <span class="text-sm font-medium">{{ Module_Alias_Name('Stripe') }}</span>
</label>