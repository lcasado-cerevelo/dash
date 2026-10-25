<?php

namespace Workdo\Paypal\Providers;

use App\Models\WorkSpace;
use Illuminate\Support\ServiceProvider;

class EbookPaymentServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function boot()
    {
        view()->composer(['ebook::frontend.checkout'], function ($view)
        {
            $slug = \Request::segment(1);
            if(!empty($slug))
            {
                try {
                    $workspace = WorkSpace::where('slug',$slug)->first();
                    $company_settings = getCompanyAllSetting($workspace->created_by,$workspace->id);

                    if(module_is_active('Paypal', $workspace->created_by) && ((isset($company_settings['paypal_payment_is_on']) ? $company_settings['paypal_payment_is_on']:'off') == 'on') && (isset($company_settings['company_paypal_client_id'])) && (isset($company_settings['company_paypal_secret_key'])))
                    {
                        $view->getFactory()->startPush('ebook_payment', view('paypal::payment.ebook_payment',compact('slug')));
                    }
                } catch (\Throwable $th)
                {

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
