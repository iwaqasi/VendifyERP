@extends('layouts.app')
@section('title', 'Navigation Menu')
@section('content')
<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h2 style="margin:0;color:#1a237e;">📋 Navigation Menu</h2><p style="margin:4px 0 0;color:#666;">Manage the website's top navigation links</p></div>
    </div>
    @if(session('success'))<div class="alert alert-success" style="border-radius:8px;">{{ session('success') }}</div>@endif
    <div class="row">
        <div class="col-md-8">
            <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <div class="card-header" style="background:#1a237e;color:white;border-radius:12px 12px 0 0;"><strong>📋 Menu Items</strong></div>
                <div class="card-body" style="padding:0;">
                    @if($items->count())
                        <table class="table table-hover" style="margin:0;">
                            <thead style="background:#f8f9fa;"><tr><th>Label</th><th>URL</th><th>Type</th><th>Status</th><th>Order</th><th>Action</th></tr></thead>
                            <tbody>
                                @foreach($items as $item)
                                <tr>
                                    <td><strong>{{ $item->label }}</strong>@if($item->label_ar)<br><small dir="rtl">{{ $item->label_ar }}</small>@endif</td>
                                    <td><code>{{ $item->url }}</code></td>
                                    <td><span class="badge badge-info">{{ $item->type }}</span></td>
                                    <td><span class="badge" style="background:{{ $item->is_active?'#4CAF50':'#ccc' }};color:white;">{{ $item->is_active?'Active':'Inactive' }}</span></td>
                                    <td>{{ $item->sort_order }}</td>
                                    <td>
                                        <form action="{{ route('cms.menus.delete', $item->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">🗑️</button></form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div style="text-align:center;padding:40px;color:#999;">No menu items yet. Add one below.</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <div class="card-header" style="background:#00BCD4;color:white;border-radius:12px 12px 0 0;"><strong>+ Add Menu Item</strong></div>
                <div class="card-body">
                    <form action="{{ route('cms.menus.store') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3"><label>Label (English)</label><input type="text" name="label" class="form-control" required placeholder="Home"></div>
                        <div class="form-group mb-3"><label>Label (Arabic)</label><input type="text" name="label_ar" class="form-control" dir="rtl" placeholder="الرئيسية"></div>
                        <div class="form-group mb-3"><label>URL</label><input type="text" name="url" class="form-control" required placeholder="/" value="/"></div>
                        <div class="form-group mb-3"><label>Type</label><select name="type" class="form-control"><option value="custom">Custom Link</option><option value="page">CMS Page</option><option value="category">Category</option><option value="blog">Blog</option></select></div>
                        <button type="submit" class="btn btn-success" style="width:100%;">💾 Add Item</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
