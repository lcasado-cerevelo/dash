    <div class="col-sm-12 col-lg-12 col-md-12">
        <div class="p-3 border shadow-md rounded-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="theme-avtar w-8 h-8">
                        <img src="{{ get_module_img('Stripe') }}" alt="" class="img-user" style="max-width: 100%">
                    </div>
                    <div class="ms-3">
                        <label for="stripe-payment">
                            <h5 class="mb-0 text-capitalize pointer">{{ Module_Alias_Name('Stripe') }}</h5>
                        </label>
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input payment_method input-checkbox" name="payment_method" id="stripe-payment"
                        type="radio" data-payment-action="{{ route('bookings.pay.with.stripe',[$slug]) }}">
                </div>
            </div>
        </div>
    </div>
