import { useRef, useState } from 'react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import RichTextEditor from '@/Components/RichTextEditor';
import { useAutoTranslate } from '@/hooks/useAutoTranslate';
import { useTranslation } from '@/hooks/useTranslation';

// The auto-translate API (MyMemory) only understands plain text, so HTML from the rich text
// editor is stripped down to text before being sent, and the translated result comes back as a
// single plain paragraph — formatting on auto-filled locales is lost and the vendor can
// reapply bold/italic/lists there manually, same as they would on any freshly-typed field.
function htmlToPlainText(html) {
    if (typeof document === 'undefined') return String(html ?? '').replace(/<[^>]*>/g, '');
    const div = document.createElement('div');
    div.innerHTML = String(html ?? '');
    return (div.textContent || div.innerText || '').trim();
}

function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function plainTextToHtml(text) {
    const trimmed = text.trim();
    if (!trimmed) return '';
    return `<p>${escapeHtml(trimmed).replace(/\n+/g, '</p><p>')}</p>`;
}

export const PRODUCT_LOCALES = [
    { code: 'en', labelKey: 'English' },
    { code: 'zh', labelKey: 'Chinese' },
    { code: 'fr', labelKey: 'French' },
];

function snapshot(map) {
    return {
        zh: map?.zh ?? '',
        en: map?.en ?? '',
        fr: map?.fr ?? '',
    };
}

function TranslateStatus({ status, __ }) {
    if (status === 'translating') {
        return <span className="text-xs text-orange-600">{__('Auto-translating…')}</span>;
    }
    if (status === 'done') {
        return <span className="text-xs text-tb-green">{__('Other languages updated')}</span>;
    }
    if (status === 'partial') {
        return <span className="text-xs text-amber-600">{__('Some languages still empty — edit and leave the field again')}</span>;
    }
    if (status === 'error') {
        return <span className="text-xs text-red-500">{__('Translation unavailable — fill other languages manually')}</span>;
    }
    return (
        <span className="text-xs text-neutral-400">
            {__('Type in one language, then leave the field — the others fill in automatically')}
        </span>
    );
}

export function LocaleTabs({ locales = PRODUCT_LOCALES, active, onChange, values, __ }) {
    return (
        <div className="flex gap-1 rounded-lg bg-neutral-100 p-1" role="tablist">
            {locales.map((locale) => {
                const filled = Boolean(String(values?.[locale.code] ?? '').trim());
                const isActive = active === locale.code;

                return (
                    <button
                        key={locale.code}
                        type="button"
                        role="tab"
                        aria-selected={isActive}
                        onClick={() => onChange(locale.code)}
                        className={`relative flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition ${
                            isActive
                                ? 'bg-white text-orange-600 shadow-sm'
                                : 'text-neutral-500 hover:text-neutral-800'
                        }`}
                    >
                        {__(locale.labelKey)}
                        {filled && (
                            <span
                                className={`h-1.5 w-1.5 rounded-full ${isActive ? 'bg-orange-500' : 'bg-tb-green'}`}
                                title="Has content"
                            />
                        )}
                    </button>
                );
            })}
        </div>
    );
}

/**
 * Tabbed multilingual input — auto-translates only after the vendor edits text
 * and leaves the field. Switching language tabs never rewrites anything.
 */
export default function TranslatableLocaleField({
    values,
    onChange,
    errors = {},
    errorKeyPrefix,
    multiline = false,
    richText = false,
    rows = 4,
    placeholders = {},
    idPrefix = 'field',
    compact = false,
}) {
    const { __ } = useTranslation();
    const [activeLocale, setActiveLocale] = useState('en');
    const [draft, setDraft] = useState(() => snapshot(values));

    const draftRef = useRef(draft);
    const onChangeRef = useRef(onChange);
    const activeLocaleRef = useRef(activeLocale);
    const dirtyRef = useRef(false);

    draftRef.current = draft;
    onChangeRef.current = onChange;
    activeLocaleRef.current = activeLocale;

    const pushUp = (next) => {
        draftRef.current = next;
        setDraft(next);
        onChangeRef.current(next);
    };

    const { status, translate } = useAutoTranslate({
        onApply: (updates, sourceLocale) => {
            const current = draftRef.current;
            const formattedUpdates = richText
                ? Object.fromEntries(Object.entries(updates).map(([locale, text]) => [locale, plainTextToHtml(text)]))
                : updates;
            pushUp({
                ...current,
                ...formattedUpdates,
                // Hard-pin the locale that triggered this request.
                [sourceLocale]: current[sourceLocale],
            });
        },
    });

    const handleChange = (value) => {
        const locale = activeLocaleRef.current;
        dirtyRef.current = true;
        pushUp({ ...draftRef.current, [locale]: value });
    };

    const handleBlur = () => {
        if (!dirtyRef.current) return;
        dirtyRef.current = false;
        const locale = activeLocaleRef.current;
        const raw = draftRef.current[locale] ?? '';
        translate(richText ? htmlToPlainText(raw) : raw, locale);
    };

    const label = __(PRODUCT_LOCALES.find((l) => l.code === activeLocale)?.labelKey);
    const fieldId = `${idPrefix}_${activeLocale}`;
    const errorMessage = errorKeyPrefix
        ? (errors[`${errorKeyPrefix}.${activeLocale}`] ?? errors[errorKeyPrefix])
        : undefined;

    return (
        <div>
            <LocaleTabs
                locales={PRODUCT_LOCALES}
                active={activeLocale}
                onChange={setActiveLocale}
                values={draft}
                __={__}
            />
            <div className={`mt-2 flex items-center justify-between gap-2 ${compact ? 'mb-1' : 'mb-2'}`}>
                <TranslateStatus status={status} __={__} />
            </div>
            <div>
                {!compact && <InputLabel htmlFor={fieldId} value={label} />}
                {richText ? (
                    <div className={compact ? '' : 'mt-1'}>
                        <RichTextEditor
                            id={fieldId}
                            value={draft[activeLocale] ?? ''}
                            placeholder={placeholders[activeLocale] ?? ''}
                            onChange={handleChange}
                            onBlur={handleBlur}
                        />
                    </div>
                ) : multiline ? (
                    <textarea
                        id={fieldId}
                        rows={rows}
                        className={`${compact ? '' : 'mt-1'} block w-full rounded-md border-neutral-300 shadow-sm focus:border-orange-500 focus:ring-orange-500`}
                        value={draft[activeLocale] ?? ''}
                        placeholder={placeholders[activeLocale] ?? ''}
                        onChange={(e) => handleChange(e.target.value)}
                        onBlur={handleBlur}
                    />
                ) : (
                    <TextInput
                        id={fieldId}
                        className={`${compact ? '' : 'mt-1'} block w-full`}
                        value={draft[activeLocale] ?? ''}
                        placeholder={placeholders[activeLocale] ?? ''}
                        onChange={(e) => handleChange(e.target.value)}
                        onBlur={handleBlur}
                    />
                )}
                <InputError message={errorMessage} className="mt-1" />
            </div>
        </div>
    );
}
