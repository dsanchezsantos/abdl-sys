import { useState, useRef, useEffect } from 'react';

type Option = {
    value: string;
    label: string;
};

type Props = {
    options: Option[];
    selected: string[];
    onChange: (selected: string[]) => void;
    placeholder?: string;
};

export default function MultiSelect({ options, selected, onChange, placeholder = 'Selecione...' }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    // Detect click outside to close dropdown
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const toggleOption = (value: string) => {
        if (selected.includes(value)) {
            onChange(selected.filter(item => item !== value));
        } else {
            onChange([...selected, value]);
        }
    };

    const handleSelectAll = () => {
        const filteredValues = filteredOptions.map(opt => opt.value);
        // Add only the options that match search filter if not already selected
        const newSelected = Array.from(new Set([...selected, ...filteredValues]));
        onChange(newSelected);
    };

    const handleClearAll = () => {
        const filteredValues = filteredOptions.map(opt => opt.value);
        // Remove only the options that match search filter
        onChange(selected.filter(val => !filteredValues.includes(val)));
    };

    const filteredOptions = options.filter(opt =>
        opt.label.toLowerCase().includes(search.toLowerCase())
    );

    // Find label for a given value
    const getLabel = (value: string) => {
        const opt = options.find(o => o.value === value);
        return opt ? opt.label : value;
    };

    return (
        <div ref={containerRef} className="relative w-full text-left font-manrope">
            {/* Display / Trigger */}
            <div
                onClick={() => setIsOpen(!isOpen)}
                className="w-full min-h-[38px] px-3 py-1.5 bg-slate-50/50 border border-slate-200 hover:border-slate-300 focus-within:border-primary/50 focus-within:ring-1 focus-within:ring-primary/20 rounded-xl text-xs font-semibold text-primary transition-all cursor-pointer flex items-center justify-between gap-2"
            >
                <div className="flex flex-wrap gap-1 items-center max-w-[90%]">
                    {selected.length === 0 ? (
                        <span className="text-primary/30 font-medium select-none">{placeholder}</span>
                    ) : selected.length <= 2 ? (
                        selected.map(val => (
                            <span 
                                key={val} 
                                className="inline-flex items-center gap-1 bg-primary/10 text-primary px-2 py-0.5 rounded-lg text-[10px] font-extrabold"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    toggleOption(val);
                                }}
                            >
                                {getLabel(val)}
                                <span className="material-symbols-outlined text-[10px] hover:text-red-500 font-bold">close</span>
                            </span>
                        ))
                    ) : (
                        <span className="bg-primary text-white px-2.5 py-0.5 rounded-lg text-[10px] font-extrabold shadow-sm shadow-primary/10">
                            {selected.length} selecionados
                        </span>
                    )}
                </div>
                <span className="material-symbols-outlined text-primary/40 text-[18px] transition-transform duration-200 select-none" style={{ transform: isOpen ? 'rotate(180deg)' : 'rotate(0)' }}>
                    keyboard_arrow_down
                </span>
            </div>

            {/* Dropdown Menu */}
            {isOpen && (
                <div className="absolute left-0 right-0 mt-2 bg-white border border-slate-100 rounded-xl shadow-xl z-50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-150 flex flex-col max-h-64">
                    {/* Search Input */}
                    <div className="p-2 border-b border-slate-100 bg-slate-50/50 flex gap-2 items-center">
                        <div className="relative flex-1">
                            <span className="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-primary/30 text-[16px]">search</span>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Filtrar opções..."
                                className="w-full pl-8 pr-3 py-1 bg-white border border-slate-200 focus:border-primary/50 focus:ring-1 focus:ring-primary/20 rounded-lg text-xs font-semibold text-primary transition-all placeholder:text-primary/20"
                                onClick={(e) => e.stopPropagation()}
                            />
                        </div>
                    </div>

                    {/* Bulk Selection Actions */}
                    <div className="px-3 py-2 border-b border-slate-50 bg-slate-50/20 flex items-center justify-between text-[10px] font-extrabold text-primary/60 uppercase tracking-wider">
                        <button type="button" onClick={handleSelectAll} className="hover:text-primary transition-colors">Selecionar Todos</button>
                        <button type="button" onClick={handleClearAll} className="hover:text-primary transition-colors text-right">Limpar</button>
                    </div>

                    {/* Options List */}
                    <div className="overflow-y-auto divide-y divide-slate-50 flex-1">
                        {filteredOptions.length > 0 ? (
                            filteredOptions.map((opt) => {
                                const isSelected = selected.includes(opt.value);
                                return (
                                    <div
                                        key={opt.value}
                                        onClick={() => toggleOption(opt.value)}
                                        className="px-4 py-2 hover:bg-slate-50 transition-colors flex items-center justify-between cursor-pointer group"
                                    >
                                        <span className={`text-xs font-semibold transition-colors ${isSelected ? 'text-primary font-bold' : 'text-primary/70 group-hover:text-primary'}`}>
                                            {opt.label}
                                        </span>
                                        <div className={`w-4 h-4 rounded border flex items-center justify-center transition-all ${isSelected ? 'bg-primary border-primary text-white' : 'border-slate-200 group-hover:border-slate-300'}`}>
                                            {isSelected && <span className="material-symbols-outlined text-[12px] font-extrabold">check</span>}
                                        </div>
                                    </div>
                                );
                            })
                        ) : (
                            <div className="px-4 py-6 text-center text-primary/30 italic text-xs">
                                Nenhuma opção encontrada.
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
