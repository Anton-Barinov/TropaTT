#!/usr/bin/env python3
"""
Convert hardcoded Russian strings in page-api-bindings.js to window.CRM.i18n.t() calls.

Uses character-by-character tokenization to correctly handle string literal boundaries.
"""
import re, sys

CYRILLIC_TO_LATIN = {
    'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'e',
    'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'i', 'к': 'k', 'л': 'l', 'м': 'm',
    'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
    'ф': 'f', 'х': 'h', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'sh', 'ъ': '',
    'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya',
    'А': 'A', 'Б': 'B', 'В': 'V', 'Г': 'G', 'Д': 'D', 'Е': 'E', 'Ё': 'E',
    'Ж': 'ZH', 'З': 'Z', 'И': 'I', 'Й': 'I', 'К': 'K', 'Л': 'L', 'М': 'M',
    'Н': 'N', 'О': 'O', 'П': 'P', 'Р': 'R', 'С': 'S', 'Т': 'T', 'У': 'U',
    'Ф': 'F', 'Х': 'H', 'Ц': 'TS', 'Ч': 'CH', 'Ш': 'SH', 'Щ': 'SH', 'Ъ': '',
    'Ы': 'Y', 'Ь': '', 'Э': 'E', 'Ю': 'YU', 'Я': 'YA',
}

def transliterate(text):
    result = []
    for ch in text:
        if ch in CYRILLIC_TO_LATIN:
            result.append(CYRILLIC_TO_LATIN[ch])
        else:
            result.append(ch)
    return ''.join(result)

def make_key(russian_text, used_keys):
    latin = transliterate(russian_text)
    latin = re.sub(r'[^a-zA-Z0-9\s]+', ' ', latin)
    key = latin.lower().strip()
    key = re.sub(r'\s+', '_', key)
    parts = key.split('_')
    parts = [p for p in parts if len(p) > 0]
    key = '_'.join(parts[:6])
    if len(key) > 60:
        key = key[:60].rstrip('_')
    if not key:
        key = 'str'
    full_key = f'js.pab.{key}'
    if full_key in used_keys:
        n = 2
        while f'{full_key}_{n}' in used_keys:
            n += 1
        full_key = f'{full_key}_{n}'
    used_keys.add(full_key)
    return full_key

def detect_cyrillic(s):
    for ch in s:
        if '\u0400' <= ch <= '\u04FF':
            return True
    return False

def count_html_tags(s):
    """Count HTML tags in a string."""
    return len(re.findall(r'</?[a-zA-Z]', s))

def extract_text_content(s):
    """Extract text content from HTML, stripping tags."""
    # Only use this for key generation - strip all HTML tags
    return re.sub(r'<[^>]+>', '', s).strip()

def is_complex_html(s):
    """
    Determine if a string contains complex HTML that should be handled separately.
    Skip strings with:
    - 4+ HTML tags (deeper nesting)
    - Form elements (input, select, textarea, option)
    - Attribute-heavy tags (>2 attributes on any tag)
    """
    if not re.search(r'<[a-zA-Z/]', s):
        return False  # No HTML at all
    
    # Complex form elements
    if re.search(r'<(input|select|textarea|option|form)\b', s):
        return True
    
    # 4+ HTML tags = complex
    if count_html_tags(s) >= 4:
        return True
    
    # Check for tags with many attributes
    tags = re.findall(r'<[a-zA-Z][^>]*>', s)
    for tag in tags:
        attr_count = len(re.findall(r'\s+\w+\s*=', tag))
        if attr_count > 2:
            return True
    
    return False

def escape_js_string(s):
    """Escape a string for use inside single-quoted JS string literal."""
    return s.replace('\\', '\\\\').replace("'", "\\'")

def is_in_existing_call(lines, line_idx, char_pos):
    """Check if position is inside _t(), kanbanT(), or window.CRM.i18n.t()."""
    line = lines[line_idx]
    before_text = line[:char_pos]
    pos = len(before_text) - 1
    depth = 0
    found_paren_idx = -1
    for j in range(pos, -1, -1):
        if before_text[j] == ')':
            depth += 1
        elif before_text[j] == '(':
            if depth == 0:
                found_paren_idx = j
                break
            depth -= 1
    if found_paren_idx < 0:
        return False
    before_paren = before_text[:found_paren_idx].rstrip()
    func_names = ['_t', 'kanbanT', 'window.CRM.i18n.t', 'CRM.i18n.t', 'i18n.t']
    for name in func_names:
        if before_paren.endswith(name):
            return True
    return False

def replace_strings_in_file(filepath, start_line=13200, end_line=19800, dry_run=False):
    with open(filepath, 'r', encoding='utf-8') as f:
        lines = f.readlines()
    
    total_lines = len(lines)
    print(f"Total lines: {total_lines}")
    
    # Collect existing keys
    existing_keys = set()
    for line in lines:
        for m in re.finditer(r"window\.CRM\.i18n\.t\('([^']+)'", line):
            existing_keys.add(m.group(1))
        for m in re.finditer(r'window\.CRM\.i18n\.t\("([^"]+)"', line):
            existing_keys.add(m.group(1))
    
    used_keys = existing_keys.copy()
    print(f"Existing i18n keys found: {len(existing_keys)}")
    
    start_idx = start_line - 1
    end_idx = min(end_line - 1, total_lines - 1)
    
    output_lines = list(lines)
    modified_count = 0
    skip_reason_count = {'html': 0, 'in_t_call': 0, 'no_cyrillic': 0, 'empty': 0, 'already_contains_t': 0}
    
    for line_idx in range(start_idx, end_idx + 1):
        line = lines[line_idx]
        i = 0
        line_changed = False
        new_line = ''
        
        while i < len(line):
            ch = line[i]
            
            # Single-line comment
            if ch == '/' and i + 1 < len(line) and line[i+1] == '/':
                new_line += line[i:]
                break
            
            # Block comment
            if ch == '/' and i + 1 < len(line) and line[i+1] == '*':
                end_pos = line.find('*/', i + 2)
                if end_pos >= 0:
                    new_line += line[i:end_pos+2]
                    i = end_pos + 2
                else:
                    new_line += line[i:]
                    break
                continue
            
            # Template literal (backtick) - pass through verbatim, no conversion
            if ch == '`':
                new_line += '`'
                i += 1
                while i < len(line):
                    c = line[i]
                    if c == '\\' and i + 1 < len(line):
                        new_line += line[i:i+2]
                        i += 2
                    elif c == '`':
                        new_line += '`'
                        i += 1
                        break
                    elif c == '$' and i + 1 < len(line) and line[i+1] == '{':
                        new_line += '${'
                        i += 2
                        depth = 1
                        while i < len(line) and depth > 0:
                            if line[i] == '{':
                                depth += 1
                            elif line[i] == '}':
                                depth -= 1
                            new_line += line[i]
                            i += 1
                    else:
                        new_line += c
                        i += 1
                continue
            
            # Single and double quote strings
            if ch in ["'", '"']:
                quote_char = ch
                start_i = i
                i += 1
                content_chars = []
                while i < len(line):
                    if line[i] == '\\':
                        content_chars.append(line[i])
                        i += 1
                        if i < len(line):
                            content_chars.append(line[i])
                            i += 1
                    elif line[i] == quote_char:
                        i += 1
                        break
                    else:
                        content_chars.append(line[i])
                        i += 1
                else:
                    # Multi-line string - leave as-is
                    new_line += line[start_i:]
                    i = len(line)
                    break
                
                content = ''.join(content_chars)
                
                if not detect_cyrillic(content):
                    new_line += line[start_i:i]
                    continue
                
                if is_in_existing_call(output_lines, line_idx, start_i):
                    skip_reason_count['in_t_call'] += 1
                    new_line += line[start_i:i]
                    continue
                
                if 'window.CRM.i18n.t' in content or '_t(' in content or 'kanbanT(' in content:
                    skip_reason_count['already_contains_t'] += 1
                    new_line += line[start_i:i]
                    continue
                
                has_html = bool(re.search(r'<[a-zA-Z/]', content))
                if has_html:
                    if is_complex_html(content):
                        skip_reason_count['html'] += 1
                        new_line += line[start_i:i]
                        continue
                    # Simple HTML string - generate key from text content
                    text_key = extract_text_content(content)
                    if not text_key:
                        skip_reason_count['html'] += 1
                        new_line += line[start_i:i]
                        continue
                    key = make_key(text_key, used_keys)
                else:
                    key = make_key(content, used_keys)
                
                escaped_content = escape_js_string(content)
                replacement = f"window.CRM.i18n.t('{key}', '{escaped_content}')"
                
                new_line += replacement
                modified_count += 1
                line_changed = True
                continue
            
            # All other characters
            new_line += ch
            i += 1
        
        if line_changed:
            output_lines[line_idx] = new_line
    
    print(f"\nDone. Lines {start_line}-{end_line}:")
    print(f"  Lines processed: {end_idx - start_idx + 1}")
    print(f"  Strings replaced: {modified_count}")
    print(f"  Skipped (HTML): {skip_reason_count['html']}")
    print(f"  Skipped (in t() call): {skip_reason_count['in_t_call']}")
    print(f"  Skipped (already contains t): {skip_reason_count['already_contains_t']}")
    
    if dry_run:
        return output_lines, modified_count
    
    output_path = filepath + '.new'
    with open(output_path, 'w', encoding='utf-8') as f:
        f.writelines(output_lines)
    print(f"\nWritten to: {output_path}")
    
    print("\n--- First 30 sample changes ---")
    changes_shown = 0
    for idx in range(start_idx, end_idx + 1):
        if lines[idx] != output_lines[idx] and changes_shown < 30:
            print(f"L{idx+1}: {lines[idx].rstrip()}")
            print(f"    -> {output_lines[idx].rstrip()}")
            print()
            changes_shown += 1
    
    return output_lines, modified_count

if __name__ == '__main__':
    filepath = '/Users/bps/sites/crm.ru/web/assets/js/page-api-bindings.js'
    dry_run = '--dry-run' in sys.argv
    output_lines, count = replace_strings_in_file(filepath, start_line=13200, end_line=19800, dry_run=dry_run)
    if count == 0:
        print("\nWARNING: No strings were replaced. Check for issues.")
    else:
        print(f"\n{count} strings converted. Output written to {filepath}.new")
