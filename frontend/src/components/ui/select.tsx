import * as SelectPrimitive from '@radix-ui/react-select';
import { Check, ChevronDown, ChevronUp } from 'lucide-react';
import {
  Children,
  isValidElement,
  useRef,
  useState,
  type ReactNode,
  type ReactElement,
} from 'react';

type Option = { value: string; label: ReactNode; disabled?: boolean };
const EMPTY = '__neobridge_empty_option__';

// Option children keep form/filter call sites compact; only Radix renders the popup.
export function Select({
  children,
  value,
  onChange,
  disabled,
  required,
  name,
  ...label
}: {
  children: ReactNode;
  value: string;
  onChange: (event: { target: { value: string } }) => void;
  disabled?: boolean;
  required?: boolean;
  name?: string;
  'aria-label'?: string;
}) {
  const trigger = useRef<HTMLButtonElement>(null);
  const [container, setContainer] = useState<HTMLElement | undefined>();
  const options: Option[] = [];
  function collect(nodes: ReactNode) {
    Children.forEach(nodes, (node) => {
      if (!isValidElement(node)) return;
      const element = node as ReactElement<{
        value?: string;
        disabled?: boolean;
        children?: ReactNode;
      }>;
      if (element.type === 'option')
        options.push({
          value: String(element.props.value ?? ''),
          label: element.props.children,
          disabled: element.props.disabled,
        });
      else if (element.props.children) collect(element.props.children);
    });
  }
  collect(children);
  const selected = options.find((option) => option.value === value);
  return (
    <SelectPrimitive.Root
      value={value || (required ? '' : EMPTY)}
      onValueChange={(next) => onChange({ target: { value: next === EMPTY ? '' : next } })}
      disabled={disabled}
      required={required}
      name={name}
      onOpenChange={(open) => {
        if (open) setContainer(trigger.current?.closest('dialog') ?? undefined);
      }}
    >
      <SelectPrimitive.Trigger
        ref={trigger}
        className="select-trigger"
        {...label}
        aria-required={required}
      >
        <SelectPrimitive.Value>{selected?.label ?? 'Pilih...'}</SelectPrimitive.Value>
        <SelectPrimitive.Icon>
          <ChevronDown size={16} />
        </SelectPrimitive.Icon>
      </SelectPrimitive.Trigger>
      <SelectPrimitive.Portal container={container}>
        <SelectPrimitive.Content
          className="select-content"
          position="popper"
          sideOffset={5}
          collisionPadding={12}
        >
          <SelectPrimitive.ScrollUpButton className="select-scroll">
            <ChevronUp size={16} />
          </SelectPrimitive.ScrollUpButton>
          <SelectPrimitive.Viewport>
            {options.map((option) => (
              <SelectPrimitive.Item
                key={option.value}
                className="select-item"
                value={option.value || EMPTY}
                disabled={option.disabled}
              >
                <SelectPrimitive.ItemText>{option.label}</SelectPrimitive.ItemText>
                <SelectPrimitive.ItemIndicator>
                  <Check size={16} />
                </SelectPrimitive.ItemIndicator>
              </SelectPrimitive.Item>
            ))}
          </SelectPrimitive.Viewport>
          <SelectPrimitive.ScrollDownButton className="select-scroll">
            <ChevronDown size={16} />
          </SelectPrimitive.ScrollDownButton>
        </SelectPrimitive.Content>
      </SelectPrimitive.Portal>
    </SelectPrimitive.Root>
  );
}
