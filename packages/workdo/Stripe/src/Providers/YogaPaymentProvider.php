<?php

namespace Workdo\Stripe\Providers;

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
                            if (module_is_active('Stripe', $workspace->created_by) && ((isset($company_settings['stripe_is_on']) ? $company_settings['stripe_is_on'] : 'off') == 'on') && (isset($company_settings['stripe_key'])) && (isset($company_settings['stripe_secret']))) {
                                $view->getFactory()->startPush('yoga_course_payment', view('stripe::payment.yoga_payment_booking', compact('workspace')));
                            }
                        } catch (\Throwable $th) {
                        }
                    }
                } catch (\Throwable $th) {
                }
            });
        }
}