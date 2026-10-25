<?php

namespace Workdo\Paypal\Providers;

use App\Models\WorkSpace;
use Illuminate\Support\ServiceProvider;

class YogaPaymentProvider extends ServiceProvider
{
    public function boot()
    {
        view()->composer(['yoga-classes::storefront.checkout'], function ($view) {
            try {
                $ids = \Request::segment(3);
                if (!empty($ids)) {
                    try {
                        $workspace = WorkSpace::where('slug', $ids)->first();
                        $company_settings = getCompanyAllSetting($workspace->created_by, $workspace->id);
                        if (module_is_active('Paypal', $workspace->created_by) && ($company_settings['paypal_payment_is_on']  == 'on') && ($company_settings['company_paypal_client_id']) && ($company_settings['company_paypal_secret_key'])) {
                            $view->getFactory()->startPush('yoga_course_payment', view('paypal::payment.yoga_payment_booking', compact('workspace')));
                        }
                    } catch (\Throwable $th) {
                    }
                }
            } catch (\Throwable $th) {
            }
        });
    }
}