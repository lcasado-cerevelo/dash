 <label for="paypal-payment"
        class="block border border-gray-200 rounded-lg p-4 hover:border-primary cursor-pointer transition-all duration-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full overflow-hidden bg-white border">
                    <img src="{{ get_module_img('Paypal') }}" alt="PayPal Logo"  class="object-contain w-full h-full" />
                </div>
                <div>
                    <h5 class="text-base font-medium text-gray-800">{{ Module_Alias_Name('Paypal') }}</h5>
                </div>
            </div>
            <input type="radio" name="payment_method" id="paypal-payment"
                class="text-primary focus:ring-0 focus:outline-none payment_method"
                data-payment-action="{{ route('beauty.spa.pay.with.paypal', [$slug]) }}" />
        </div>
</label>