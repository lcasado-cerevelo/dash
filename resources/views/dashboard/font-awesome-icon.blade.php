@extends('layouts.main')

@section('page-title')
    {{ __('Icon Picker Demo') }}
@endsection

@php
    $socialLinks = [
        ['icon' => 'fab fa-facebook', 'url' => 'https://facebook.com'],
        ['icon' => 'fab fa-twitter', 'url' => 'https://twitter.com'],
        ['icon' => 'fab fa-instagram', 'url' => 'https://instagram.com'],
        ['icon' => 'fab fa-linkedin', 'url' => 'https://linkedin.com'],
        ['icon' => 'fab fa-youtube', 'url' => 'https://youtube.com']
    ];

    $services = [];
@endphp

@section('content')
<form>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Icon Picker Examples') }}</h5>
                </div>
                <div class="card-body">

                    <!-- Single Icon Picker -->
                    <div class="mb-4">
                        <h6>{{ __('Single Icon Picker') }}</h6>
                        <x-icon-picker name="main_icon" label="Main Icon" />
                    </div>

                    <!-- Multiple Icon Pickers -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <x-icon-picker name="primary_icon" label="Primary Icon" />
                        </div>
                        <div class="col-md-6">
                            <x-icon-picker name="secondary_icon" label="Secondary Icon" />
                        </div>
                    </div>

                    <!-- Social Links Repeater -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6>{{ __('Social Links (Repeater)') }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="repeater icon-repeater" id="social-repeater">
                                <div data-repeater-list="social">
                                    @forelse(empty($socialLinks) ? [['icon' => '', 'url' => '']] : $socialLinks as $social)
                                    <div data-repeater-item class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <x-icon-picker name="icon" label="Icon" value="{{ $social['icon'] ?? '' }}" />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">URL</label>
                                                <input type="url" name="url" class="form-control" value="{{ $social['url'] ?? '' }}" placeholder="https://example.com">
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="button" data-repeater-delete class="btn btn-danger btn-sm">Remove</button>
                                            </div>
                                        </div>
                                    </div>
                                    @empty
                                    @endforelse
                                </div>
                                <button type="button" data-repeater-create class="btn btn-primary btn-sm">Add Link</button>
                            </div>
                        </div>
                    </div>

                    <!-- Services Repeater -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6>{{ __('Services (Repeater)') }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="repeater icon-repeater" id="services-repeater">
                                <div data-repeater-list="services">
                                    @forelse(empty($services) ? [['icon' => '', 'name' => '', 'price' => '']] : $services as $service)
                                    <div data-repeater-item class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <x-icon-picker name="icon" label="Service Icon" value="{{ $service['icon'] ?? '' }}" />
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Service Name</label>
                                                <input type="text" name="name" class="form-control" value="{{ $service['name'] ?? '' }}" placeholder="Service name">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Price</label>
                                                <input type="number" name="price" class="form-control" value="{{ $service['price'] ?? '' }}" placeholder="0.00">
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="button" data-repeater-delete class="btn btn-danger btn-sm">Remove</button>
                                            </div>
                                        </div>
                                    </div>
                                    @empty
                                    @endforelse
                                </div>
                                <button type="button" data-repeater-create class="btn btn-primary btn-sm">Add Service</button>
                            </div>
                        </div>
                    </div>

                    <!-- Gallery Repeater with Images -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6>{{ __('Gallery Items (Repeater with Images)') }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="repeater icon-repeater" id="gallery-repeater">
                                <div data-repeater-list="gallery">
                                    <div data-repeater-item class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <x-icon-picker name="icon" label="Icon" />
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Title</label>
                                                <input type="text" name="title" class="form-control" placeholder="Item title">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Image</label>
                                                <input type="file" name="image" class="form-control mb-2" accept="image/*" onchange="previewImage(this)">
                                                <div class="image-preview" style="display:none;">
                                                    <img src="" alt="Preview" style="max-width:100px;max-height:100px;border:1px solid #ddd;border-radius:4px;">
                                                </div>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="button" data-repeater-delete class="btn btn-danger btn-sm">Remove</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" data-repeater-create class="btn btn-primary btn-sm">Add Gallery Item</button>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6>{{ __('Gallery Items (Repeater with Images)') }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="repeater icon-repeater" id="gallery-repeater">
                                <div data-repeater-list="gallery">
                                    <div data-repeater-item class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <x-icon-picker name="icon" label="Icon" />
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Title</label>
                                                <input type="text" name="title" class="form-control" placeholder="Item title">
                                            </div>
                                            <div class="col-md-4 d-flex align-items-center">
                                                <div class="flex-grow-1">
                                                    <label class="form-label">Image</label>
                                                    <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                                                </div>
                                                <div class="ms-2 image-preview" style="display:none;">
                                                    <img src="" alt="Preview" style="width:60px;height:60px;object-fit:cover;border:1px solid #ddd;border-radius:4px;">
                                                </div>
                                            </div>

                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="button" data-repeater-delete class="btn btn-danger btn-sm">Remove</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" data-repeater-create class="btn btn-primary btn-sm">Add Gallery Item</button>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonials Repeater -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6>{{ __('Testimonials (Repeater with Star Rating)') }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="repeater icon-repeater" id="testimonials-repeater">
                                <div data-repeater-list="testimonials">
                                    <div data-repeater-item class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <label class="form-label">Name</label>
                                                <input type="text" name="name" class="form-control" placeholder="Customer name">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Position</label>
                                                <input type="text" name="position" class="form-control" placeholder="Job title">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Star Rating</label>
                                                <select name="rating" class="form-control">
                                                    <option value="1">⭐ 1 Star</option>
                                                    <option value="2">⭐⭐ 2 Stars</option>
                                                    <option value="3">⭐⭐⭐ 3 Stars</option>
                                                    <option value="4">⭐⭐⭐⭐ 4 Stars</option>
                                                    <option value="5" selected>⭐⭐⭐⭐⭐ 5 Stars</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Avatar</label>
                                                <input type="file" name="avatar" class="form-control" accept="image/*">
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="button" data-repeater-delete class="btn btn-danger btn-sm">Remove</button>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <label class="form-label">Testimonial</label>
                                                <textarea name="testimonial" class="form-control" rows="3" placeholder="Customer testimonial..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" data-repeater-create class="btn btn-primary btn-sm">Add Testimonial</button>
                            </div>
                        </div>
                    </div>

                    <!-- Multiple Feature Icons -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6>{{ __('Feature Icons') }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <x-icon-picker name="features[0][icon]" label="Feature 1" />
                                </div>
                                <div class="col-md-4">
                                    <x-icon-picker name="features[1][icon]" label="Feature 2" />
                                </div>
                                <div class="col-md-4">
                                    <x-icon-picker name="features[2][icon]" label="Feature 3" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save All Icons</button>

                </div>
            </div>
        </div>
    </div>
</form>

@endsection

@push('scripts')
<script src="{{ asset('js/jquery-ui.min.js') }}"></script>
<script src="{{ asset('js/jquery.repeater.min.js') }}"></script>
<script src="{{ asset('js/icon-repeater.js') }}"></script>
@endpush
