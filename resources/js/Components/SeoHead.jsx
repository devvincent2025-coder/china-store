import { Head, usePage } from '@inertiajs/react';

/**
 * Renders the exact same tag set as resources/views/partials/seo-meta.blade.php, from the exact
 * same `seo` prop shape (see App\Support\Seo\Seo) — this is what keeps the browser tab title and
 * meta tags correct across Inertia's client-side SPA navigations, since the Blade partial only
 * ever renders once, on a page's very first full-page load.
 *
 * Every `head-key` here matches a `inertia="<key>"` attribute in the Blade partial exactly, so
 * Inertia's client-side head reconciliation replaces the server-rendered tag in place instead of
 * appending a duplicate — see @inertiajs/core's Renderer.update(), which matches elements by that
 * attribute value.
 *
 * Falls back to the site-wide `seoDefaults` shared prop (see HandleInertiaRequests) for any page
 * that hasn't been given bespoke `seo` prop treatment yet, so nothing ever regresses to a blank tab
 * title.
 */
export default function SeoHead({ seo = {} }) {
    const { seoDefaults = {} } = usePage().props;
    const merged = { ...seoDefaults, ...seo };
    const jsonLdBlocks = [...(seoDefaults.jsonLd || []), ...(seo.jsonLd || [])];

    return (
        <Head title={merged.title}>
            <meta head-key="description" name="description" content={merged.description || ''} />
            {merged.keywords && <meta head-key="keywords" name="keywords" content={merged.keywords} />}
            <meta head-key="robots" name="robots" content={merged.robots || 'index,follow'} />
            {merged.canonical && <link head-key="canonical" rel="canonical" href={merged.canonical} />}

            {Object.entries(merged.alternates || {}).map(([hreflang, url]) => (
                <link key={hreflang} head-key={`alternate:${hreflang}`} rel="alternate" hreflang={hreflang} href={url} />
            ))}

            <meta head-key="og:site_name" property="og:site_name" content={merged.siteName || 'ChinaInNigeria'} />
            <meta head-key="og:title" property="og:title" content={merged.title || ''} />
            <meta head-key="og:description" property="og:description" content={merged.description || ''} />
            <meta head-key="og:type" property="og:type" content={merged.type || 'website'} />
            {merged.canonical && <meta head-key="og:url" property="og:url" content={merged.canonical} />}
            {merged.image && <meta head-key="og:image" property="og:image" content={merged.image} />}

            <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
            <meta head-key="twitter:title" name="twitter:title" content={merged.title || ''} />
            <meta head-key="twitter:description" name="twitter:description" content={merged.description || ''} />
            {merged.image && <meta head-key="twitter:image" name="twitter:image" content={merged.image} />}

            {merged.product && (
                <>
                    <meta head-key="product:price:amount" property="product:price:amount" content={merged.product.price} />
                    <meta head-key="product:price:currency" property="product:price:currency" content={merged.product.currency} />
                </>
            )}

            {jsonLdBlocks.map((schema, index) => (
                <script key={index} type="application/ld+json" head-key={`jsonld:${index}`}>
                    {JSON.stringify(schema)}
                </script>
            ))}
        </Head>
    );
}
