<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <title>{{ __('products.photo_capture.title') }}</title>
    @vite(['resources/js/products/photo-capture-page.js'])
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f5f5f5; margin: 0; padding: 16px;
            min-height: 100vh;
        }
        .pc-title { font-size: 1.1rem; font-weight: 700; text-align: center; margin: 4px 0 16px; }

        .pc-scan-card {
            background: #fff; border-radius: 16px; padding: 20px;
            text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        .pc-scan-input {
            width: 100%; box-sizing: border-box; padding: 16px; font-size: 1.2rem;
            border: 2px solid #ddd; border-radius: 10px; text-align: center;
            margin-top: 12px;
        }
        .pc-scan-input:focus { border-color: #141414; outline: none; }
        .pc-scan-hint { color: #888; font-size: .8rem; margin-top: 8px; }

        .pc-error {
            background: #fee; color: #900; border-radius: 10px; padding: 12px 14px;
            margin-bottom: 14px; font-size: .9rem; text-align: center;
        }

        .pc-product-card {
            background: #fff; border-radius: 16px; padding: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        .pc-product-name { font-size: 1.15rem; font-weight: 700; }
        .pc-product-sku { color: #888; font-size: .85rem; margin-top: 2px; }

        .pc-take-btn {
            width: 100%; padding: 18px; margin-top: 18px; border: none; border-radius: 12px;
            background: #141414; color: #fff; font-size: 1.1rem; font-weight: 700; cursor: pointer;
        }
        .pc-take-btn:disabled { opacity: .6; }

        .pc-photos-label { margin-top: 20px; font-size: .8rem; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: .04em; }
        .pc-photos-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 10px;
        }
        .pc-photo-thumb { position: relative; border-radius: 8px; overflow: hidden; aspect-ratio: 1; background: #eee; }
        .pc-photo-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .pc-photo-del {
            position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%;
            background: rgba(0,0,0,.6); color: #fff; border: none; font-size: .9rem; line-height: 1;
            cursor: pointer;
        }

        .pc-done-btn {
            width: 100%; padding: 14px; margin-top: 18px; border: 1px solid #ddd; border-radius: 10px;
            background: #fff; color: #141414; font-size: 1rem; font-weight: 600; cursor: pointer;
        }
    </style>
</head>
<body>
    <div x-data="productPhotoCapture(@js([
        'scan_url'          => route('product-photos.scan'),
        'store_url_template'=> route('product-photos.store', ['product' => ':id']),
        'labels'            => [
            'upload_failed'   => __('products.photo_capture.upload_failed'),
            'delete_confirm'  => __('products.photo_capture.delete_confirm'),
            'scan_not_found'  => __('products.photo_capture.scan.not_found', ['barcode' => ':barcode']),
            'photo_singular'  => __('products.photo_capture.photo_singular'),
            'photo_plural'    => __('products.photo_capture.photo_plural'),
        ],
    ]))" x-init="init()">

        <div class="pc-title">{{ __('products.photo_capture.title') }}</div>

        <template x-if="error">
            <div class="pc-error" x-text="error"></div>
        </template>

        <template x-if="!product">
            <div class="pc-scan-card">
                <div>{{ __('products.photo_capture.scan_prompt') }}</div>
                <input type="text" x-ref="scanInput" x-model="scanQuery"
                       @keydown.enter="onScanEnter()"
                       class="pc-scan-input" autocomplete="off">
                <div class="pc-scan-hint">{{ __('products.photo_capture.scan_hint') }}</div>
            </div>
        </template>

        <template x-if="product">
            <div class="pc-product-card">
                <div class="pc-product-name" x-text="product.name"></div>
                <div class="pc-product-sku" x-text="product.sku"></div>

                <input type="file" accept="image/*" capture="environment" x-ref="photoInput"
                       style="display:none" @change="onPhotoChosen($event)">

                <button type="button" class="pc-take-btn" :disabled="uploading" @click="takePhoto()">
                    <span x-show="!uploading">{{ __('products.photo_capture.take_photo') }}</span>
                    <span x-show="uploading">{{ __('products.photo_capture.uploading') }}</span>
                </button>

                <template x-if="photos.length > 0">
                    <div>
                        <div class="pc-photos-label" x-text="photos.length + ' ' + (photos.length === 1 ? labels.photo_singular : labels.photo_plural)"></div>
                        <div class="pc-photos-grid">
                            <template x-for="photo in photos" :key="photo.id">
                                <div class="pc-photo-thumb">
                                    <img :src="photo.url" alt="">
                                    <button type="button" class="pc-photo-del" @click="deletePhoto(photo)">×</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <button type="button" class="pc-done-btn" @click="doneScanNext()">
                    {{ __('products.photo_capture.done_scan_next') }}
                </button>
            </div>
        </template>
    </div>
</body>
</html>
