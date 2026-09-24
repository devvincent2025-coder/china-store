import { useMemo } from 'react';
import ReactQuill from 'react-quill-new';
import 'react-quill-new/dist/quill.snow.css';

const MODULES = {
    toolbar: [
        ['bold', 'italic', 'underline'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['clean'],
    ],
};

const FORMATS = ['bold', 'italic', 'underline', 'list'];

/**
 * Minimal WYSIWYG editor for product descriptions — bold/italic/underline/lists only, so the
 * HTML it produces stays within what HtmlSanitizer (app/Support/Html/HtmlSanitizer.php) allows
 * through on save and what Products/Show.jsx renders on the buyer-facing page.
 */
export default function RichTextEditor({ value, onChange, onBlur, placeholder, id }) {
    const modules = useMemo(() => MODULES, []);

    return (
        <div id={id} className="rich-text-editor rounded-md border border-neutral-300 focus-within:border-orange-500 focus-within:ring-1 focus-within:ring-orange-500">
            <ReactQuill
                theme="snow"
                value={value ?? ''}
                onChange={onChange}
                onBlur={onBlur}
                modules={modules}
                formats={FORMATS}
                placeholder={placeholder}
            />
        </div>
    );
}
