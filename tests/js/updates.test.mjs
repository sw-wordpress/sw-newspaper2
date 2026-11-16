import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

async function load(due = false, button = true) {
  document.body.innerHTML = button ? '<button id="swnewspaper2-verified-updates-check"></button><span id="swnewspaper2-verified-updates-result" hidden></span>' : '';
  window.swnewspaper2VerifiedUpdates = {url: '/wp-admin/admin-ajax.php', action: 'swnewspaper2_check_github_updates', nonce:'test', due, messages:{checking:'Checking', done:'Done', failed:'Failed'}};
  vi.resetModules();
  await import('../../assets/js/sw-newspaper2-updates.js');
  window.dispatchEvent(new Event('load'));
}
const click = () => document.querySelector('button').click();
const flush = async () => { await Promise.resolve(); await Promise.resolve(); await Promise.resolve(); await Promise.resolve(); await Promise.resolve(); };
beforeEach(() => { vi.useFakeTimers(); vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ok:true,json:async()=>({data:{message:'Verified'}})})); });
afterEach(() => { vi.useRealTimers(); vi.unstubAllGlobals(); delete window.swnewspaper2VerifiedUpdates; delete window.requestIdleCallback; });
it('does nothing without configuration', async () => { vi.resetModules(); await import('../../assets/js/sw-newspaper2-updates.js'); expect(fetch).not.toHaveBeenCalled(); });
it('manual requests are single flight, use the nonce and show the result', async () => {
  let resolve; fetch.mockReturnValue(new Promise(r => { resolve = r; }));
  await load(); click();
  expect(document.querySelector('button').disabled).toBe(true);
  document.querySelector('button').disabled=false; click();
  expect(fetch).toHaveBeenCalledTimes(1);
  expect(Object.fromEntries(fetch.mock.calls[0][1].body)).toEqual({action:'swnewspaper2_check_github_updates',nonce:'test',manual:'1'});
  resolve({ok:true,json:async()=>({data:{message:'Verified'}})}); await flush();
  expect(document.querySelector('span').textContent).toBe('Verified');
  expect(document.querySelector('button').disabled).toBe(false);
});
it('reports server refusals and empty responses', async () => {
  fetch.mockResolvedValue({ok:true,json:async()=>({success:false,data:{message:'Throttled'}})});
  await load(); click(); await flush(); expect(document.querySelector('span').textContent).toBe('Throttled');
  fetch.mockResolvedValue({ok:true,json:async()=>({})}); click(); await flush(); expect(document.querySelector('span').textContent).toBe('Done');
});
it('handles network and HTTP failures', async () => {
  await load(); fetch.mockRejectedValue(new Error('network')); click(); await flush(); expect(document.querySelector('span').textContent).toBe('Failed');
  fetch.mockResolvedValue({ok:false}); click(); await flush(); expect(document.querySelector('span').textContent).toBe('Failed');
});
it('defers the daily check with idle scheduling without controls', async () => {
  window.requestIdleCallback=vi.fn(callback => window.setTimeout(callback,5));
  await load(true,false); expect(fetch).not.toHaveBeenCalled();
  await vi.advanceTimersByTimeAsync(5); expect(fetch).toHaveBeenCalledTimes(1);
  expect(fetch.mock.calls[0][1].body.get('manual')).toBe('0');
});
it('uses a timer when idle scheduling is unavailable and aborts stalled fetches', async () => {
  await load(true); await vi.advanceTimersByTimeAsync(1499); expect(fetch).not.toHaveBeenCalled();
  await vi.advanceTimersByTimeAsync(1); expect(fetch).toHaveBeenCalledTimes(1);
  fetch.mockImplementation((url,{signal})=>new Promise((resolve,reject)=>signal.addEventListener('abort',()=>reject(new Error('aborted')))));
  click(); await vi.advanceTimersByTimeAsync(90000); expect(document.querySelector('span').textContent).toBe('Failed');
});

it('waits for page load before scheduling HTTP', async () => {
  Object.defineProperty(document,'readyState',{configurable:true,value:'loading'});
  vi.resetModules();
  window.swnewspaper2VerifiedUpdates={url:'/ajax',action:'swnewspaper2_check_github_updates',nonce:'test',due:true};
  await import('../../assets/js/sw-newspaper2-updates.js');
  await vi.advanceTimersByTimeAsync(2000); expect(fetch).not.toHaveBeenCalled();
  window.dispatchEvent(new Event('load')); await vi.advanceTimersByTimeAsync(1500); expect(fetch).toHaveBeenCalledTimes(1);
  Object.defineProperty(document,'readyState',{configurable:true,value:'complete'});
});
