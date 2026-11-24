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
export default function TopPicksSection({ topPicks = [], eyebrow, title }) {
    const { __ } = useTranslation();

    if (!topPicks || topPicks.length === 0) return null;

    return (
        <section className="mt-8">
            <div className="mb-4 flex items-end justify-between gap-3">
                <div>
                    <p className="font-ui text-[11px] font-bold uppercase tracking-[0.18em] text-tb">{eyebrow || __('Sponsored')}</p>
                    <h2 className="font-display text-[20px] font-bold tracking-tight text-tb-ink sm:text-[22px]">
                        {title || __('Top Picks — Recommended for you')}
                    </h2>
                </div>
            </div>

            <div className="no-scrollbar -mx-1.5 flex snap-x gap-3 overflow-x-auto px-1.5 pb-3">
                {topPicks.map((pick) => (
                    <a
                        key={pick.token}
                        href={route('go.visit', pick.token)}
                        className="group relative block aspect-[4/5] w-44 shrink-0 snap-start overflow-hidden rounded-2xl bg-tb-peach shadow-soft transition duration-300 hover:-translate-y-1 hover:shadow-float sm:w-52"
                    >
                        {pick.banner_url ? (
                            <img src={pick.banner_url} alt="" className="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                        ) : (
                            <div className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-tb-peach via-[#FFD2B8] to-[#FFB59A] font-display text-5xl font-extrabold text-white/90">
                                {pick.store?.name ? t(pick.store.name).charAt(0) : '?'}
                            </div>
                        )}
                        <div className="absolute inset-0 bg-gradient-to-t from-black/45 via-transparent to-transparent" />
                        <div className="absolute inset-x-2 bottom-2 rounded-xl bg-white/90 px-3 py-2 shadow-soft">
                            <p className="truncate text-[13px] font-bold text-tb-ink">{pick.store ? t(pick.store.name) : __('Store')}</p>
                            <p className="flex items-center gap-1 truncate text-[11px] text-neutral-600">
                                {pick.store?.city ? pick.store.city : __('Visit store')}
                                <span className="ml-auto text-tb transition group-hover:translate-x-0.5">→</span>
                            </p>
                        </div>
                    </a>
                ))}
            </div>
        </section>
    );
}
