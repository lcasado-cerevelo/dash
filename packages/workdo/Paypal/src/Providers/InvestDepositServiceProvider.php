<?php

namespace Workdo\Paypal\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\WorkSpace;


class InvestDepositServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */


    public function boot()
    {
        view()->composer(['investment-system::frontend.investor.deposit'], function ($view) {
            $slug = \Request::segment(1);
            if (!empty($slug)) {
                $workspace = WorkSpace::where('slug', $slug)->first();
                $company_settings = getCompanyAllSetting($workspace->created_by, $workspace->id);

                if (module_is_active('Paypal', $workspace->created_by) && ((isset($company_settings['paypal_payment_is_on']) ? $company_settings['paypal_payment_is_on'] : 'off') == 'on') && (isset($company_settings['company_paypal_client_id'])) && (isset($company_settings['company_paypal_secret_key']))) {
                    $view->getFactory()->startPush('invest_deposit_payment', view('paypal::payment.invest_deposit_payment', compact('slug')));
                }
            }
        });
    }

    public function register()
    {
        //
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
