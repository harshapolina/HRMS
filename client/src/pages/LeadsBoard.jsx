import React, { useEffect, useState, useRef } from 'react';
import axios from 'axios';
import io from 'socket.io-client';
import { Send, Phone, User, Calendar, MapPin, Layers, MessageSquare, Plus, CheckCircle, Clock, X, ToggleLeft, ToggleRight, Sparkles } from 'lucide-react';

const LeadsBoard = () => {
  const [leads, setLeads] = useState([]);
  const [stats, setStats] = useState({ total: 0, pending: 0, contacted: 0, interested: 0, booked: 0, eoi: 0 });
  const [selectedLead, setSelectedLead] = useState(null);
  const [showChat, setShowChat] = useState(false);
  const [chatMessages, setChatMessages] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  const [remarkText, setRemarkText] = useState('');
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [projectFilter, setProjectFilter] = useState('');
  
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(15);
  const [totalPages, setTotalPages] = useState(1);
  const [loading, setLoading] = useState(false);
  const [chatLoading, setChatLoading] = useState(false);
  const [autoReply, setAutoReply] = useState(true);

  // Ingestion Modal
  const [showIngestModal, setShowIngestModal] = useState(false);
  const [ingestForm, setIngestForm] = useState({
    name: '',
    email: '',
    number: '',
    location: '',
    project_name: '',
    subsource_of_lead: ''
  });

  const socketRef = useRef();
  const chatEndRef = useRef();

  // Load user data to pass roles/tablename
  const user = JSON.parse(localStorage.getItem('user') || '{}');

  useEffect(() => {
    fetchLeads();
  }, [page, limit, search, statusFilter, projectFilter]);

  // Configure Socket.io
  useEffect(() => {
    socketRef.current = io('http://localhost:5000');

    socketRef.current.on('whatsapp_message', (data) => {
      // If the message belongs to the currently selected lead chat, append it live
      if (selectedLead && data.lead_id === selectedLead._id) {
        setChatMessages((prev) => [...prev, data.message]);
      }
      // Refresh the lead list to update unread badges
      fetchLeads();
    });

    return () => {
      socketRef.current.disconnect();
    };
  }, [selectedLead]);

  useEffect(() => {
    scrollToBottom();
  }, [chatMessages]);

  const scrollToBottom = () => {
    chatEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  const fetchLeads = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/leads', {
        params: {
          page,
          limit,
          search,
          status: statusFilter,
          project: projectFilter,
          tablename: user.tablename,
          user_role: user.user_type
        }
      });
      setLeads(res.data.data);
      setTotalPages(res.data.totalPages);
      if (res.data.stats) {
        setStats(res.data.stats);
      }
    } catch (err) {
      console.error('Error fetching leads', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchChatHistory = async (lead) => {
    setChatLoading(true);
    try {
      const res = await axios.get(`/api/whatsapp/chat?lead_id=${lead._id}`);
      setChatMessages(res.data.messages);
      setAutoReply(res.data.auto_reply !== false);
    } catch (err) {
      console.error('Error fetching chat history', err);
    } finally {
      setChatLoading(false);
    }
  };

  const handleOpenChat = (lead) => {
    setSelectedLead(lead);
    fetchChatHistory(lead);
    setShowChat(true);
  };

  const handleSendMessage = async (e) => {
    e.preventDefault();
    if (!newMessage.trim() || !selectedLead) return;

    const text = newMessage;
    setNewMessage('');

    // Append temporarily
    const tempEntry = {
      role: 'esha',
      direction: 'OUTBOUND',
      message: text,
      time: new Date()
    };
    setChatMessages((prev) => [...prev, tempEntry]);

    try {
      await axios.post('/api/whatsapp/send', {
        lead_id: selectedLead._id,
        text
      });
    } catch (err) {
      alert('Send failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const handleAddRemark = async (e) => {
    e.preventDefault();
    if (!remarkText.trim() || !selectedLead) return;

    try {
      await axios.post(`/api/leads/${selectedLead._id}/remarks`, {
        text: remarkText,
        created_by: user.tablename || 'system'
      });
      setRemarkText('');
      // Refresh lead details
      const res = await axios.get(`/api/leads/${selectedLead._id}`);
      setSelectedLead(res.data);
      fetchLeads();
    } catch (err) {
      alert('Remark failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const handleUpdateStatus = async (id, newStatus) => {
    try {
      await axios.put(`/api/leads/${id}/status`, { status: newStatus });
      fetchLeads();
      if (selectedLead && selectedLead._id === id) {
        setSelectedLead({ ...selectedLead, status: newStatus });
      }
    } catch (err) {
      alert('Status update failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const handleToggleAutoReply = async () => {
    if (!selectedLead) return;
    const nextVal = !autoReply;
    setAutoReply(nextVal);
    try {
      await axios.post('/api/whatsapp/auto-reply', {
        lead_id: selectedLead._id,
        enabled: nextVal
      });
    } catch (err) {
      console.error(err);
    }
  };

  const handleIngestSubmit = async (e) => {
    e.preventDefault();
    try {
      const res = await axios.post('/api/leads/ingest', ingestForm);
      if (res.data.status === 'duplicate') {
        alert(`Duplicate Lead: ${res.data.message}`);
      } else {
        alert(`Lead ingested successfully! Assigned to: ${res.data.assigned_user}`);
      }
      setShowIngestModal(false);
      setIngestForm({
        name: '',
        email: '',
        number: '',
        location: '',
        project_name: '',
        subsource_of_lead: ''
      });
      fetchLeads();
    } catch (err) {
      alert('Ingestion failed: ' + (err.response?.data?.message || err.message));
    }
  };

  return (
    <div className="flex flex-col xl:flex-row gap-6 relative h-[calc(100vh-140px)]">
      {/* Main Leads Board Panel */}
      <div className="flex-1 flex flex-col bg-white border border-slate-100 rounded-2xl overflow-hidden shadow-sm">
        
        {/* Top Header & Stats */}
        <div className="p-4 border-b border-slate-100 bg-slate-50/25 grid grid-cols-2 md:grid-cols-6 gap-3">
          <div className="p-2 border border-slate-100 rounded-xl bg-white text-center">
            <span className="text-xxs uppercase font-semibold text-slate-400">Total Leads</span>
            <span className="block text-lg font-bold text-slate-700 mt-0.5">{stats.total}</span>
          </div>
          <div className="p-2 border border-slate-100 rounded-xl bg-white text-center">
            <span className="text-xxs uppercase font-semibold text-slate-400">Pending</span>
            <span className="block text-lg font-bold text-amber-600 mt-0.5">{stats.pending}</span>
          </div>
          <div className="p-2 border border-slate-100 rounded-xl bg-white text-center">
            <span className="text-xxs uppercase font-semibold text-slate-400">Contacted</span>
            <span className="block text-lg font-bold text-blue-600 mt-0.5">{stats.contacted}</span>
          </div>
          <div className="p-2 border border-slate-100 rounded-xl bg-white text-center">
            <span className="text-xxs uppercase font-semibold text-slate-400">Interested</span>
            <span className="block text-lg font-bold text-purple-600 mt-0.5">{stats.interested}</span>
          </div>
          <div className="p-2 border border-slate-100 rounded-xl bg-white text-center">
            <span className="text-xxs uppercase font-semibold text-slate-400">EOI</span>
            <span className="block text-lg font-bold text-pink-600 mt-0.5">{stats.eoi}</span>
          </div>
          <div className="p-2 border border-slate-100 rounded-xl bg-white text-center">
            <span className="text-xxs uppercase font-semibold text-slate-400">Booked</span>
            <span className="block text-lg font-bold text-emerald-600 mt-0.5">{stats.booked}</span>
          </div>
        </div>

        {/* Filter controls */}
        <div className="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-white">
          <div className="flex gap-2 flex-1 sm:max-w-md">
            <input
              type="text"
              placeholder="Search by name, number..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs w-full"
            />
          </div>
          <div className="flex gap-3 items-center">
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs"
            >
              <option value="">All Statuses</option>
              <option value="Pending">Pending</option>
              <option value="Contacted">Contacted</option>
              <option value="Interested">Interested</option>
              <option value="EOI">EOI</option>
              <option value="Booked">Booked</option>
            </select>
            <input
              type="text"
              placeholder="Project..."
              value={projectFilter}
              onChange={(e) => setProjectFilter(e.target.value)}
              className="px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs w-28"
            />
            <button
              onClick={() => setShowIngestModal(true)}
              className="px-4 py-2 bg-brand-500 text-white rounded-xl text-xs font-bold hover:bg-brand-600 flex items-center gap-1.5 transition-all"
            >
              <Plus className="w-4.5 h-4.5" /> Ingest Lead
            </button>
          </div>
        </div>

        {/* Leads List */}
        <div className="flex-1 overflow-y-auto">
          <div className="divide-y divide-slate-100">
            {loading && leads.length === 0 ? (
              <div className="text-center py-20">
                <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-brand-500 mx-auto"></div>
              </div>
            ) : leads.length === 0 ? (
              <div className="text-center py-20 text-slate-400 text-sm">
                No active leads found matching criteria.
              </div>
            ) : (
              leads.map((lead) => (
                <div
                  key={lead._id}
                  onClick={() => handleOpenChat(lead)}
                  className={`p-4 hover:bg-slate-50/50 cursor-pointer flex items-center justify-between transition-all ${selectedLead?._id === lead._id ? 'bg-brand-50/20 border-l-4 border-brand-500' : ''}`}
                >
                  <div className="space-y-1.5 min-w-0 flex-1 pr-4">
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-slate-800 truncate">{lead.name}</span>
                      {lead.unread_wa_count > 0 && (
                        <span className="bg-emerald-500 text-white font-bold text-xxs px-2 py-0.5 rounded-full animate-bounce">
                          {lead.unread_wa_count} new
                        </span>
                      )}
                    </div>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-slate-500 text-xs font-semibold">
                      <span className="flex items-center gap-1"><Phone className="w-3.5 h-3.5" /> {lead.number}</span>
                      <span className="flex items-center gap-1 text-indigo-600"><Layers className="w-3.5 h-3.5" /> {lead.project || 'General'}</span>
                    </div>
                  </div>

                  <div className="flex items-center gap-3">
                    <select
                      value={lead.status}
                      onClick={(e) => e.stopPropagation()}
                      onChange={(e) => handleUpdateStatus(lead._id, e.target.value)}
                      className={`px-3 py-1 rounded-full text-xs font-semibold border ${lead.status === 'Pending' ? 'bg-amber-50 text-amber-700 border-amber-200' : lead.status === 'Booked' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : lead.status === 'Interested' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-slate-50 text-slate-700 border-slate-200'}`}
                    >
                      <option value="Pending">Pending</option>
                      <option value="Contacted">Contacted</option>
                      <option value="Interested">Interested</option>
                      <option value="EOI">EOI</option>
                      <option value="Booked">Booked</option>
                    </select>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Paginator */}
        <div className="p-3 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
          <button
            onClick={() => setPage(Math.max(1, page - 1))}
            disabled={page === 1}
            className="px-3 py-1.5 border border-slate-200 rounded-xl text-xs font-bold disabled:opacity-50"
          >
            Prev
          </button>
          <span className="text-xs font-semibold text-slate-500">Page {page} of {totalPages}</span>
          <button
            onClick={() => setPage(Math.min(totalPages, page + 1))}
            disabled={page === totalPages}
            className="px-3 py-1.5 border border-slate-200 rounded-xl text-xs font-bold disabled:opacity-50"
          >
            Next
          </button>
        </div>
      </div>

      {/* Right Detail & Chat Sidebar */}
      {selectedLead && showChat && (
        <div className="w-full xl:w-96 flex flex-col border border-slate-100 bg-white rounded-2xl shadow-sm overflow-hidden h-full">
          {/* Header */}
          <div className="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-900 text-white">
            <div className="min-w-0">
              <h4 className="font-bold text-sm truncate">{selectedLead.name}</h4>
              <p className="text-xxs text-slate-400 flex items-center gap-1 mt-0.5">
                <Phone className="w-3 h-3" /> {selectedLead.number}
              </p>
            </div>
            <button onClick={() => setShowChat(false)} className="p-1 hover:bg-slate-800 rounded-lg">
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Details Scroll */}
          <div className="flex-1 overflow-y-auto p-4 space-y-6">
            
            {/* Auto reply switch */}
            <div className="p-3 bg-brand-50/20 border border-brand-500/10 rounded-xl flex items-center justify-between">
              <span className="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                <Sparkles className="w-4 h-4 text-brand-500" /> Esha AI Autoreply
              </span>
              <button onClick={handleToggleAutoReply} className="text-brand-500 focus:outline-none">
                {autoReply ? <ToggleRight className="w-10 h-10" /> : <ToggleLeft className="w-10 h-10 text-slate-400" />}
              </button>
            </div>

            {/* Chat Messages */}
            <div className="space-y-4">
              <h5 className="text-slate-400 text-xxs uppercase font-semibold tracking-wider">WhatsApp Transcript</h5>
              <div className="border border-slate-100 rounded-xl p-3 bg-slate-50/50 h-64 overflow-y-auto space-y-2 flex flex-col">
                {chatLoading ? (
                  <div className="animate-spin rounded-full h-6 w-6 border-t-2 border-brand-500 mx-auto my-auto"></div>
                ) : chatMessages.length === 0 ? (
                  <div className="text-center text-xs text-slate-400 my-auto">No WhatsApp logs recorded.</div>
                ) : (
                  chatMessages.map((msg, idx) => (
                    <div
                      key={idx}
                      className={`max-w-[75%] p-2.5 rounded-2xl text-xs ${msg.role === 'esha' ? 'bg-brand-500 text-white self-end rounded-tr-none' : 'bg-slate-200 text-slate-800 self-start rounded-tl-none'}`}
                    >
                      <div>{msg.message}</div>
                      <div className="text-xxs opacity-75 mt-1 text-right">
                        {msg.time ? new Date(msg.time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}
                      </div>
                    </div>
                  ))
                )}
                <div ref={chatEndRef} />
              </div>

              {/* Chat Input */}
              <form onSubmit={handleSendMessage} className="flex gap-2">
                <input
                  type="text"
                  placeholder="Type WhatsApp message..."
                  value={newMessage}
                  onChange={(e) => setNewMessage(e.target.value)}
                  className="flex-1 px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs"
                />
                <button
                  type="submit"
                  className="p-2 bg-brand-500 hover:bg-brand-600 text-white rounded-xl transition-all"
                >
                  <Send className="w-4 h-4" />
                </button>
              </form>
            </div>

            {/* Remarks Section */}
            <div className="space-y-4 border-t border-slate-100 pt-4">
              <h5 className="text-slate-400 text-xxs uppercase font-semibold tracking-wider">Activity Remarks</h5>
              
              <div className="space-y-2 max-h-40 overflow-y-auto pr-1">
                {selectedLead.remarks && selectedLead.remarks.length > 0 ? (
                  selectedLead.remarks.map((rmk, idx) => (
                    <div key={idx} className="p-2.5 bg-slate-50 border border-slate-100 rounded-lg text-xs">
                      <p className="text-slate-800">{rmk.text}</p>
                      <div className="flex justify-between text-xxs text-slate-400 mt-1 font-semibold">
                        <span>By: {rmk.created_by}</span>
                        <span>{new Date(rmk.created_at).toLocaleDateString()}</span>
                      </div>
                    </div>
                  ))
                ) : (
                  <p className="text-center text-xs text-slate-400">No remarks logged.</p>
                )}
              </div>

              <form onSubmit={handleAddRemark} className="space-y-2">
                <textarea
                  placeholder="Add a progress remark..."
                  value={remarkText}
                  onChange={(e) => setRemarkText(e.target.value)}
                  className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs h-16 resize-none"
                />
                <button
                  type="submit"
                  className="w-full py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition-all"
                >
                  Save Remark
                </button>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Ingestion Modal */}
      {showIngestModal && (
        <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-white border border-slate-200 rounded-2xl w-full max-w-md shadow-2xl p-6 space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-slate-800 font-bold text-base">Manual Lead Ingestion</h3>
              <button onClick={() => setShowIngestModal(false)} className="p-1 hover:bg-slate-100 rounded-lg text-slate-500">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleIngestSubmit} className="space-y-4">
              <div>
                <label className="block text-slate-600 text-xs font-semibold mb-1">Lead Name *</label>
                <input
                  type="text"
                  required
                  value={ingestForm.name}
                  onChange={(e) => setIngestForm({ ...ingestForm, name: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs"
                />
              </div>
              <div>
                <label className="block text-slate-600 text-xs font-semibold mb-1">Lead Number (10 digits) *</label>
                <input
                  type="text"
                  required
                  value={ingestForm.number}
                  onChange={(e) => setIngestForm({ ...ingestForm, number: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs"
                />
              </div>
              <div>
                <label className="block text-slate-600 text-xs font-semibold mb-1">Email Address</label>
                <input
                  type="email"
                  value={ingestForm.email}
                  onChange={(e) => setIngestForm({ ...ingestForm, email: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs"
                />
              </div>
              <div>
                <label className="block text-slate-600 text-xs font-semibold mb-1">Location</label>
                <input
                  type="text"
                  value={ingestForm.location}
                  onChange={(e) => setIngestForm({ ...ingestForm, location: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs"
                />
              </div>
              <div>
                <label className="block text-slate-600 text-xs font-semibold mb-1">Project Name *</label>
                <input
                  type="text"
                  required
                  value={ingestForm.project_name}
                  onChange={(e) => setIngestForm({ ...ingestForm, project_name: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs"
                />
              </div>
              <div>
                <label className="block text-slate-600 text-xs font-semibold mb-1">Subsource of Lead</label>
                <input
                  type="text"
                  value={ingestForm.subsource_of_lead}
                  onChange={(e) => setIngestForm({ ...ingestForm, subsource_of_lead: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs"
                />
              </div>

              <div className="pt-2 flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setShowIngestModal(false)}
                  className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-brand-500 text-white rounded-xl text-xs font-bold hover:bg-brand-600"
                >
                  Ingest Lead
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default LeadsBoard;
