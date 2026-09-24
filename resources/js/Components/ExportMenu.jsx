import Dropdown from '@/Components/Dropdown';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * "Export ▾" button offering a PDF and an Excel download for the given named route. Plain <a>
 * tags on purpose — these are real file downloads (Content-Disposition: attachment), not Inertia
 * page visits, so a normal browser navigation is what we want here.
 */
export default function ExportMenu({ routeName, params = {} }) {
    const { __ } = useTranslation();

    return (
        <Dropdown>
            <Dropdown.Trigger>
                <button
                    type="button"
                    className="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={1.5}
                        className="h-4 w-4"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"
                        />
                    </svg>
                    {__('Export')}
                </button>
            </Dropdown.Trigger>

            <Dropdown.Content align="right" width="48">
                <a
                    href={route(routeName, { ...params, format: 'pdf' })}
                    className="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100"
                >
                    {__('Export as PDF')}
                </a>
                <a
                    href={route(routeName, { ...params, format: 'xlsx' })}
                    className="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100"
                >
                    {__('Export as Excel')}
                </a>
            </Dropdown.Content>
        </Dropdown>
    );
}
