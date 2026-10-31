import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Mirrors templates/admin/tab-upload.php and tab-archive.php.
const markup = `
  <form id="upload">
    <label class="swnp-drop" for="swnp-file">
      <span class="swnp-drop__name" data-swnp-filename>No file selected</span>
      <input type="file" id="swnp-file">
    </label>
    <input type="file" id="outside">
  </form>
  <form id="delete" data-swnp-confirm><button type="submit">Delete</button></form>
  <form id="plain"><button type="submit">Save</button></form>
`;

async function loadAdmin(config) {
  if (config === undefined) {
    delete window.swnewspaper2Admin;
  } else {
    window.swnewspaper2Admin = config;
  }
  vi.resetModules();
  await import('../../assets/admin.js');
}

function chooseFiles(input, files) {
  Object.defineProperty(input, 'files', { configurable: true, value: files });
  input.dispatchEvent(new Event('change'));
}

function submit(form) {
  const event = new Event('submit', { cancelable: true });
  form.dispatchEvent(event);
  return event;
}

const label = () => document.querySelector('[data-swnp-filename]');
const config = { noFile: 'Keine Datei ausgewählt', confirmDelete: 'Wirklich löschen?' };

beforeEach(() => {
  document.body.innerHTML = markup;
});

afterEach(() => {
  vi.restoreAllMocks();
  delete window.swnewspaper2Admin;
});

describe('file picker label', () => {
  it('shows the name of the selected file', async () => {
    await loadAdmin(config);
    chooseFiles(document.getElementById('swnp-file'), [{ name: 'ausgabe-42.pdf' }, { name: 'other.pdf' }]);
    expect(label().textContent).toBe('ausgabe-42.pdf');
  });

  it('shows the localized placeholder when the selection is cleared', async () => {
    await loadAdmin(config);
    const input = document.getElementById('swnp-file');
    chooseFiles(input, [{ name: 'ausgabe-42.pdf' }]);
    chooseFiles(input, []);
    expect(label().textContent).toBe('Keine Datei ausgewählt');
  });

  it('ignores file inputs outside a drop zone', async () => {
    await loadAdmin(config);
    chooseFiles(document.getElementById('outside'), [{ name: 'other.pdf' }]);
    expect(label().textContent).toBe('No file selected');
  });

  it('renders file names as text, not HTML', async () => {
    await loadAdmin(config);
    chooseFiles(document.getElementById('swnp-file'), [{ name: '<img src=x onerror=alert(1)>.pdf' }]);
    expect(label().querySelector('img')).toBeNull();
    expect(label().textContent).toBe('<img src=x onerror=alert(1)>.pdf');
  });

  it('works without localized configuration', async () => {
    await loadAdmin(undefined);
    chooseFiles(document.getElementById('swnp-file'), []);
    expect(label().textContent).toBe('');
  });
});

describe('delete confirmation', () => {
  it('asks with the localized message and cancels when declined', async () => {
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
    await loadAdmin(config);
    expect(submit(document.getElementById('delete')).defaultPrevented).toBe(true);
    expect(confirm).toHaveBeenCalledWith('Wirklich löschen?');
  });

  it('submits when confirmed', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    await loadAdmin(config);
    expect(submit(document.getElementById('delete')).defaultPrevented).toBe(false);
  });

  it('does not ask for forms without the confirm marker', async () => {
    const confirm = vi.spyOn(window, 'confirm');
    await loadAdmin(config);
    expect(submit(document.getElementById('plain')).defaultPrevented).toBe(false);
    expect(confirm).not.toHaveBeenCalled();
  });
});
