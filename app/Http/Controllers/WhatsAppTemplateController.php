<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppTemplateController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant;
        $templates = DB::table('whatsapp_templates')
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        return view('settings.whatsapp-templates', compact('templates'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'body' => 'required|string|max:65535',
        ]);

        $tenant = auth()->user()->tenant;

        $id = DB::table('whatsapp_templates')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'body' => $request->body,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLog::log('whatsapp_template.created', null, ['name' => $request->name]);

        return redirect()->route('whatsapp-templates.edit', $id)->with('success', 'WhatsApp template created.');
    }

    public function edit($id)
    {
        $tenant = auth()->user()->tenant;
        $template = DB::table('whatsapp_templates')
            ->where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        return view('settings.whatsapp-template-edit', compact('template'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'body' => 'required|string|max:65535',
        ]);

        $tenant = auth()->user()->tenant;

        DB::table('whatsapp_templates')
            ->where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->update([
                'name' => $request->name,
                'body' => $request->body,
                'updated_at' => now(),
            ]);

        AuditLog::log('whatsapp_template.updated', null, ['name' => $request->name]);

        return redirect()->route('whatsapp-templates.edit', $id)->with('success', 'WhatsApp template updated.');
    }

    public function destroy($id)
    {
        $tenant = auth()->user()->tenant;

        $template = DB::table('whatsapp_templates')
            ->where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->first();

        if ($template) {
            DB::table('whatsapp_templates')
                ->where('id', $id)
                ->delete();

            AuditLog::log('whatsapp_template.deleted', null, ['name' => $template->name]);
        }

        return redirect()->route('whatsapp-templates.index')->with('success', 'WhatsApp template deleted.');
    }

    public function preview($id)
    {
        $tenant = auth()->user()->tenant;
        $template = DB::table('whatsapp_templates')
            ->where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        return view('settings.whatsapp-template-preview', compact('template', 'tenant'));
    }
}
