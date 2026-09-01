@extends('layouts.app')
@section('title', 'Banners')
@section('content')
<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h2 style="margin:0;color:#1a237e;">🖼️ Banners / Sliders</h2><p style="margin:4px 0 0;color:#666;">Hero banners, promo sliders, and featured images</p></div>
        <a href="{{ route('cms.banners.create') }}" class="btn btn-success">+ New Banner</a>
    </div>
    @if(session('success'))<div class="alert alert-success" style="border-radius:8px;">{{ session('success') }}</div>@endif
    <div class="row">
        @forelse($banners as $banner)
        <div class="col-md-4 mb-4">
            <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);overflow:hidden;">
                @if($banner->image)
                    <img src="{{ asset('storage/'.$banner->image) }}" style="width:100%;height:180px;object-fit:cover;">
                @else
                    <div style="width:100%;height:180px;background:linear-gradient(135deg,#00BCD4,#1a237e);display:flex;align-items:center;justify-content:center;color:white;font-size:3rem;">🖼️</div>
                @endif
                <div class="card-body">
                    <h5 style="margin:0 0 4px;">{{ $banner->title }}</h5>
                    @if($banner->subtitle)<p style="color:#666;font-size:0.9rem;margin:0 0 8px;">{{ $banner->subtitle }}</p>@endif
                    <div><span class="badge" style="background:#e3f2fd;color:#1a237e;">{{ ucfirst($banner->position) }}</span>
                    <span class="badge" style="background:{{ $banner->is_active ? '#e8f5e9' : '#ffebee' }};color:{{ $banner->is_active ? '#2E7D32' : '#c62828' }};">{{ $banner->is_active ? 'Active' : 'Inactive' }}</span>
                    <span class="badge" style="background:#f5f5f5;color:#666;">Order: {{ $banner->sort_order }}</span></div>
                    @if($banner->button_text)<p style="margin:8px 0 0;"><a href="{{ $banner->link ?? '#' }}" style="color:#00BCD4;">{{ $banner->button_text }} →</a></p>@endif
                    <div style="margin-top:12px;">
                        <a href="{{ route('cms.banners.edit', $banner->id) }}" class="btn btn-sm btn-outline-primary">✏️ Edit</a>
                        <form action="{{ route('cms.banners.delete', $banner->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">🗑️</button></form>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12" style="text-align:center;padding:60px;color:#999;">
            <div style="font-size:3rem;margin-bottom:12px;">🖼️</div>
            No banners yet. <a href="{{ route('cms.banners.create') }}">Add your first banner</a>
        </div>
        @endforelse
    </div>
</div>
@endsection
