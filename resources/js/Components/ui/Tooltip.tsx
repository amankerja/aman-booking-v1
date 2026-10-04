import React, {
    cloneElement,
    ReactElement,
    ReactNode,
    useId,
    useRef,
    useState,
} from 'react';

export interface TooltipProps {
    content: ReactNode;
    children: ReactElement<{
        onMouseEnter?: (e: React.MouseEvent) => void;
        onMouseLeave?: (e: React.MouseEvent) => void;
        onFocus?: (e: React.FocusEvent) => void;
        onBlur?: (e: React.FocusEvent) => void;
        'aria-describedby'?: string;
    }>;
    position?: 'top' | 'bottom' | 'left' | 'right';
    delay?: number;
}

export const Tooltip: React.FC<TooltipProps> = ({
    content,
    children,
    position = 'top',
    delay = 200,
}) => {
    const [isVisible, setIsVisible] = useState(false);
    const timeoutRef = useRef<NodeJS.Timeout | null>(null);
    const tooltipId = useId();

    const showTooltip = () => {
        timeoutRef.current = setTimeout(() => {
            setIsVisible(true);
        }, delay);
    };

    const hideTooltip = () => {
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }
        setIsVisible(false);
    };

    const positionClasses = {
        top: 'bottom-full left-1/2 -translate-x-1/2 mb-1.5',
        bottom: 'top-full left-1/2 -translate-x-1/2 mt-1.5',
        left: 'right-full top-1/2 -translate-y-1/2 mr-1.5',
        right: 'left-full top-1/2 -translate-y-1/2 ml-1.5',
    }[position];

    return (
        <div className="relative inline-flex">
            {cloneElement(children, {
                onMouseEnter: (e: React.MouseEvent) => {
                    children.props.onMouseEnter?.(e);
                    showTooltip();
                },
                onMouseLeave: (e: React.MouseEvent) => {
                    children.props.onMouseLeave?.(e);
                    hideTooltip();
                },
                onFocus: (e: React.FocusEvent) => {
                    children.props.onFocus?.(e);
                    showTooltip();
                },
                onBlur: (e: React.FocusEvent) => {
                    children.props.onBlur?.(e);
                    hideTooltip();
                },
                'aria-describedby': isVisible ? tooltipId : undefined,
            })}

            {isVisible && (
                <div
                    id={tooltipId}
                    role="tooltip"
                    className={`pointer-events-none absolute z-50 rounded-md bg-slate-900 px-2 py-1 text-[11px] font-medium whitespace-nowrap text-white shadow-sm transition-opacity ${positionClasses}`}
                >
                    {content}
                </div>
            )}
        </div>
    );
};
