<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cpanel;
use App\Models\Rdp;
use App\Models\Ssh;
use App\Models\Mailer;
use App\Models\Shell;
use App\Models\Smtp;
use App\Models\Lead;

class ItemController extends Controller
{
    /**
     * Display a list of items for the given type.
     *
     * @param  string  $type
     * @return \Illuminate\View\View
     */
    public function list($type = 'rdp')
    {
        // Define allowed types.
        $whitelist = ['rdp', 'cpanel', 'shell', 'ssh', 'mailer', 'smtp', 'lead'];
        if (!in_array($type, $whitelist)) {
            abort(404);
        }

        // Map each type to its corresponding model class.
        $models = [
            'rdp'    => Rdp::class,
            'cpanel' => Cpanel::class,
            'shell'  => Shell::class,
            'ssh'    => Ssh::class,
            'mailer' => Mailer::class,
            'smtp'   => Smtp::class,
            'lead'   => Lead::class,
        ];

        $modelClass = $models[$type];

        // Retrieve list of items using the model’s getList() method.
        $data = $modelClass::getList();

        // Process each item.
        $items = collect($data)->map(function ($item) {
            // Process HTTPS settings.
            if (isset($item->https)) {
                if ($item->https == 1) {
                    $item->lock  = 'fa-lock';
                    $item->https = 'https';
                    $item->color = '#18BC9C';
                } else {
                    $item->lock  = 'fa-lock-open';
                    $item->https = 'http';
                }
            }

            // Determine webmail and WHM availability.
            $item->webmail = (isset($item->webmail) && $item->webmail == 0) ? 'No' : 'Yes';
            $item->whm     = (isset($item->whm) && $item->whm == 0) ? 'No' : 'Yes';

            return $item;
        });

        // Render the view for this type.
        // For example, the view file might be at resources/views/items/rdp.blade.php.
        return view('items.' . strtolower($type), [
            'items' => $items,
        ]);
    }
}