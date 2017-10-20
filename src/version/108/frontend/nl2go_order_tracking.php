<?php

$config = $GLOBALS['DB']->executeQuery('SELECT * FROM xplugin_newsletter2go_keys', 1);

if (!empty($config->companyid)
    && !empty($config->ordertracking)
    && $config->ordertracking === 'true'
) {
    echo getTrackingScript($config->companyid);
}

function getTrackingScript($companyId)
{
    $order = $GLOBALS['bestellung'];
    $transactionData = [
        'id' => (string)$order->kBestellung,
        'affiliation' => (string)$GLOBALS['cShopName'],
        'revenue' => (string)round($order->fGesamtsumme, 2),
        'shipping' => (string)round($order->fVersand, 2),
        'tax' => (string)round($order->fSteuern, 2)
    ];

    $script = '<script id="n2g_script"> 
            !function(e,t,n,c,r,a,i) { 
                e.Newsletter2GoTrackingObject=r, 
                e[r]=e[r]||function(){(e[r].q=e[r].q||[]).push(arguments)}, 
                e[r].l=1*new Date, 
                a=t.createElement(n), 
                i=t.getElementsByTagName(n)[0], 
                a.async=1, 
                a.src=c, 
                i.parentNode.insertBefore(a,i) 
            } 
            (window,document,"script","//static-sandbox.newsletter2go.com/utils.js","n2g"); 
            n2g(\'create\', \'' . $companyId . '\'); 
            n2g(\'ecommerce:addTransaction\', ' . json_encode($transactionData) . ');';

    foreach ($order->Positionen as $product) {
        if (!isset($product->Artikel)) {
            continue;
        }
        
        $productDetails = $product->Artikel;
        $productData = [
            'id' => (string)$order->kBestellung,
            'name' => (string)$productDetails->cName,
            'sku' => (string)$productDetails->cArtNr,
            'category' => (string)$product->Category,
            'price' => (string)round($productDetails->Preise->fVKBrutto, 2),
            'quantity' => (string)$product->nAnzahl
        ];

        $script .= " 
            n2g('ecommerce:addItem', " . json_encode($productData) . ");";
    }

    return $script . ' 
            n2g(\'ecommerce:send\'); 
        </script>';
}