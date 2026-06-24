import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Megaphone, Plus, Trash2, Calendar, User, Eye, X } from 'lucide-react';

const NoticeAlerts = () => {
  const [notices, setNotices] = useState([]);
  const [loading, setLoading] = useState(false);
  const [showAddForm, setShowAddForm] = useState(false);
  const [alertMessage, setAlertMessage] = useState('');
  const [previewNotice, setPreviewNotice] = useState(null);

  useEffect(() => {
    fetchNotices();
  }, []);

  const fetchNotices = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/notices');
      setNotices(res.data || []);
    } catch (err) {
      console.error('Error fetching notices', err);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!alertMessage.trim()) return;

    try {
      await axios.post('/api/notices', { alert_message: alertMessage });
      setAlertMessage('');
      setShowAddForm(false);
      fetchNotices();
      alert('System notice created and active.');
    } catch (err) {
      alert('Failed to publish notice: ' + (err.response?.data?.message || err.message));
    }
  };

  return (
    <div className="space-y-6 text-slate-800 dark:text-slate-200">
      
      {/* Action Header */}
      <div className="flex items-center justify-between">
        <div>
          <span className="text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider block">Global Notice Manager</span>
          <span className="text-3xl font-extrabold text-slate-800 dark:text-white mt-1 block">Notice Alerts</span>
        </div>
        <button
          onClick={() => setShowAddForm(true)}
          className="px-5 py-2.5 bg-brand-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-brand-500/10 hover:bg-brand-600 flex items-center gap-2 transition-all"
        >
          <Plus className="w-4 h-4" /> Create System Alert
        </button>
      </div>

      <div className="grid grid-cols-1 gap-6">
        
        {/* Notice History List */}
        <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
          <div className="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/25 dark:bg-slate-800/50 flex items-center justify-between">
            <h3 className="text-slate-800 dark:text-white font-bold text-sm">Notice Dispatch Log</h3>
          </div>

          <div className="divide-y divide-slate-100 dark:divide-slate-800">
            {loading && notices.length === 0 ? (
              <div className="text-center py-10">
                <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-brand-500 mx-auto"></div>
              </div>
            ) : notices.length === 0 ? (
              <div className="text-center py-12 text-slate-400 dark:text-slate-500">
                <Megaphone className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-700 mb-3" />
                <p className="text-xs font-semibold">No active notices published yet.</p>
              </div>
            ) : (
              notices.map((notice) => (
                <div key={notice._id} className="p-6 hover:bg-slate-50/25 dark:hover:bg-slate-800/20 transition-all flex flex-col md:flex-row md:items-start justify-between gap-4">
                  <div className="space-y-2 max-w-3xl">
                    {/* Notice message preview/render */}
                    <div 
                      className="text-xs leading-relaxed text-slate-700 dark:text-slate-300 prose dark:prose-invert max-w-none"
                      dangerouslySetInnerHTML={{ __html: notice.alert_message }}
                    />
                    <div className="flex items-center gap-4 text-xxs text-slate-450 font-medium">
                      <span className="flex items-center gap-1"><User className="w-3 h-3" /> Published by: <span className="font-bold text-slate-600 dark:text-slate-300 capitalize">{notice.created_by}</span></span>
                      <span className="flex items-center gap-1"><Calendar className="w-3 h-3" /> {new Date(notice.createdAt).toLocaleString()}</span>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 self-end md:self-start">
                    <button
                      onClick={() => setPreviewNotice(notice)}
                      className="px-3 py-1.5 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-lg text-xxs font-bold transition-all flex items-center gap-1"
                    >
                      <Eye className="w-3.5 h-3.5" /> Preview Popup
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      </div>

      {/* Add Notice Modal */}
      {showAddForm && (
        <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-xl shadow-2xl p-6 space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-slate-800 dark:text-white font-bold text-base">Publish Global Notice</h3>
              <button onClick={() => setShowAddForm(false)} className="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-500">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Notice Message (HTML Allowed) *</label>
                <textarea
                  required
                  placeholder="<h2>Important Update</h2><p>Please complete your target sheets by end of day today...</p>"
                  value={alertMessage}
                  onChange={(e) => setAlertMessage(e.target.value)}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs h-40 font-mono bg-transparent dark:text-white"
                />
              </div>

              {/* Real-time HTML render preview */}
              {alertMessage && (
                <div className="border border-slate-200 dark:border-slate-800 rounded-xl p-4 bg-slate-50 dark:bg-slate-950">
                  <span className="text-[10px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider block mb-2">Live Render Preview</span>
                  <div 
                    className="text-xs leading-relaxed text-slate-700 dark:text-slate-350 prose dark:prose-invert max-w-none"
                    dangerouslySetInnerHTML={{ __html: alertMessage }}
                  />
                </div>
              )}

              <div className="pt-2 flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setShowAddForm(false)}
                  className="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-xl text-xs font-bold shadow-md"
                >
                  Publish to Dashboard
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Preview Notice Modal */}
      {previewNotice && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md shadow-2xl p-6 space-y-4">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
              <span className="text-rose-500 text-xs font-extrabold tracking-wider flex items-center gap-1.5 uppercase"><Megaphone className="w-4 h-4" /> System Notice Alert</span>
              <button onClick={() => setPreviewNotice(null)} className="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-500">
                <X className="w-5 h-5" />
              </button>
            </div>
            <div 
              className="text-xs text-slate-700 dark:text-slate-300 leading-relaxed py-2 prose dark:prose-invert max-w-none"
              dangerouslySetInnerHTML={{ __html: previewNotice.alert_message }}
            />
            <div className="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end">
              <button
                onClick={() => setPreviewNotice(null)}
                className="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white rounded-xl text-xs font-bold"
              >
                Close Preview
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default NoticeAlerts;
