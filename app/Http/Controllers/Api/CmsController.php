<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\Faq;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

class CmsController extends Controller
{
    /**
     * Get all active CMS pages.
     */
    public function pages(): JsonResponse
    {
        if (!SystemSetting::get('cms.enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'CMS is disabled.',
            ], 403);
        }

        $pages = CmsPage::active()
            ->select('id', 'slug', 'title', 'show_in_footer')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pages' => $pages,
            ],
        ]);
    }

    /**
     * Get a specific CMS page by slug.
     */
    public function page(string $slug): JsonResponse
    {
        if (!SystemSetting::get('cms.enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'CMS is disabled.',
            ], 403);
        }

        $page = CmsPage::where('slug', $slug)
            ->active()
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'page' => [
                    'id' => $page->id,
                    'slug' => $page->slug,
                    'title' => $page->title,
                    'content' => $page->content,
                ],
            ],
        ]);
    }

    /**
     * Get footer pages.
     */
    public function footerPages(): JsonResponse
    {
        if (!SystemSetting::get('cms.enabled', true)) {
            return response()->json([
                'success' => true,
                'data' => ['pages' => []],
            ]);
        }

        $limit = SystemSetting::get('cms.footer_limit', 5);
        $pages = CmsPage::footer()
            ->limit($limit)
            ->select('slug', 'title')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pages' => $pages,
            ],
        ]);
    }

    /**
     * Get all active FAQs.
     */
    public function faqs(): JsonResponse
    {
        $faqs = Faq::active()
            ->ordered()
            ->select('id', 'question', 'answer')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'faqs' => $faqs,
            ],
        ]);
    }
}

