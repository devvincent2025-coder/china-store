import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * "Top Picks — Recommended for you" — paid vendor placements (App\Services\Ads\AdCampaignService),
 * shown as a horizontal-scroll strip on the homepage and a few other buyer-facing pages
 * (Categories/Show.jsx, Products/Index.jsx). Every card here already counted as an impression
 * server-side (see HomeController/CategoryController/ProductController) the moment this page was
 * requested; a click goes through /ads/{id}/click, which records the click and forwards to the
 * vendor's store page.
 */
export default function TopPicksSection({ topPicks = [] }) {
    const { __ } = useTranslation();

    if (!topPicks || topPicks.length === 0) return null;

    return (
        <section className="mt-4">
            <div className="mb-3 flex items-center gap-1.5">
                <svg className="h-5 w-5 text-tb" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                </svg>
                <h2 className="font-display text-[18px] font-semibold text-tb-text">{__('Top Picks — Recommended for you')}</h2>
            </div>

            <div className="flex gap-3 overflow-x-auto pb-2" style={{ scrollbarWidth: 'thin' }}>
                {topPicks.map((pick) => (
                    <a
                        key={pick.token}
                        href={route('go.visit', pick.token)}
                        className="group block w-48 shrink-0 overflow-hidden rounded-lg border border-tb-border bg-white shadow-sm transition hover:shadow-md"
                    >
                        <div className="aspect-[4/3] w-full overflow-hidden bg-neutral-100">
                            {pick.banner_url ? (
                                <img src={pick.banner_url} alt="" className="h-full w-full object-cover transition group-hover:scale-105" />
                            ) : (
                                <div className="flex h-full w-full items-center justify-center bg-orange-50 text-3xl font-bold text-orange-300">
                                    {pick.store?.name ? t(pick.store.name).charAt(0) : '?'}
                                </div>
                            )}
                        </div>
                        <div className="p-2.5">
                            <p className="truncate text-sm font-semibold text-tb-text">{pick.store ? t(pick.store.name) : __('Store')}</p>
                            {pick.store?.city && (
                                <p className="truncate text-xs text-tb-muted">{pick.store.city}</p>
                            )}
                        </div>
                    </a>
                ))}
            </div>
        </section>
    );
}
