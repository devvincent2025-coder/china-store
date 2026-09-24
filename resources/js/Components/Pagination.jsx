import { Link } from '@inertiajs/react';

/**
 * Renders Previous/Next + page-number controls for a Laravel paginate() response
 * (has `data`, `links[]` = [{url,label,active}], and `meta{current_page,last_page,...}`).
 *
 * `onStart`/`onFinish` are optional Inertia visit-lifecycle passthroughs — a page that wants to
 * show a skeleton while a new page of results loads (pagination doesn't unmount/remount the
 * component, since it's the same route, so a local `loading` state gated by these survives the
 * visit cleanly) can pass them in; pages that don't care just omit them.
 */
export default function Pagination({ paginator, onStart, onFinish }) {
    if (!paginator || !paginator.links || paginator.links.length <= 3) return null;

    const currentPage = paginator.meta?.current_page ?? paginator.current_page;
    const lastPage = paginator.meta?.last_page ?? paginator.last_page;

    if (!lastPage || lastPage <= 1) return null;

    return (
        <nav className="mt-8 flex flex-wrap items-center justify-center gap-1">
            {paginator.links.map((link, i) => {
                const label = link.label
                    .replace('&laquo; Previous', '‹ Prev')
                    .replace('Next &raquo;', 'Next ›');

                if (!link.url) {
                    return (
                        <span
                            key={i}
                            className="rounded-md px-3 py-1.5 text-sm text-neutral-300"
                            dangerouslySetInnerHTML={{ __html: label }}
                        />
                    );
                }

                return (
                    <Link
                        key={i}
                        href={link.url}
                        preserveScroll
                        preserveState
                        onStart={onStart}
                        onFinish={onFinish}
                        className={`rounded-md px-3 py-1.5 text-sm font-medium ${
                            link.active
                                ? 'bg-orange-600 text-white'
                                : 'text-neutral-600 hover:bg-orange-50 hover:text-orange-600'
                        }`}
                        dangerouslySetInnerHTML={{ __html: label }}
                    />
                );
            })}
        </nav>
    );
}
