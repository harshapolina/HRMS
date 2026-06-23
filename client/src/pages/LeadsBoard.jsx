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
  const [activeUsers, setActiveUsers] = useState([]);

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
  const chatContainerRef = useRef();

  // Load user data to pass roles/tablename
  const user = JSON.parse(localStorage.getItem('user') || '{}');

  const fetchLeadsRef = useRef(null);
  const selectedLeadRef = useRef(null);

  useEffect(() => {
    fetchLeadsRef.current = fetchLeads;
    selectedLeadRef.current = selectedLead;
  });

  useEffect(() => {
    fetchLeads();
  }, [page, limit, search, statusFilter, projectFilter]);

  useEffect(() => {
    fetchActiveUsers();
  }, []);

  const fetchActiveUsers = async () => {
    try {
      const res = await axios.get('/api/users/all-active');
      setActiveUsers(res.data);
    } catch (err) {
      console.error('Error fetching active users', err);
    }
  };

  // Configure Socket.io
  useEffect(() => {
    const socket = io();
    socketRef.current = socket;

    socket.on('whatsapp_message', (data) => {
      // If the message belongs to the currently selected lead chat, append it live
      if (selectedLeadRef.current && data.lead_id === selectedLeadRef.current._id) {
        setChatMessages((prev) => [...prev, data.message]);
      }
      // Refresh the lead list to update unread badges
      if (fetchLeadsRef.current) fetchLeadsRef.current();
    });

    socket.on('lead_update', (data) => {
      console.log('Real-time lead update received. Reloading leads...');
      if (fetchLeadsRef.current) fetchLeadsRef.current();
      if (selectedLeadRef.current && data && data._id === selectedLeadRef.current._id) {
        setSelectedLead(data);
      }
    });

    return () => {
      socket.disconnect();
    };
  }, []);

  useEffect(() => {
    scrollToBottom();
  }, [chatMessages]);

  const scrollToBottom = () => {
    if (chatContainerRef.current) {
      chatContainerRef.current.scrollTop = chatContainerRef.current.scrollHeight;
    }
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

  const handleUpdateAssignee = async (leadId, newAssignee) => {
    try {
      await axios.put(`/api/leads/${leadId}/assignee`, { assign_to_user: newAssignee });
      fetchLeads();
      if (selectedLead && selectedLead._id === leadId) {
        setSelectedLead({ ...selectedLead, assign_to_user: newAssignee });
      }
    } catch (err) {
      alert('Assignee update failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const getAssigneeName = (tablename) => {
    if (!tablename || tablename === 'unassigned') return 'Unassigned';
    const found = activeUsers.find(u => u.tablename === tablename);
    return found ? found.username : tablename.replace('USR_', '').replace(/_/g, ' ');
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
      <div className="flex-1 flex flex-col bg-canvas border border-hairline-soft rounded-lg overflow-hidden shadow-sm">
        
        {/* Top Header & Stats */}
        <div className="p-4 border-b border-hairline bg-surface-soft/50">
          <div className="stat-grid md:grid-cols-6 gap-3">
          <div className="stat-card text-center">
            <span className="stat-card-label">Total Leads</span>
            <span className="stat-card-value">{stats.total}</span>
          </div>
          <div className="stat-card text-center">
            <span className="stat-card-label">Pending</span>
            <span className="stat-card-value text-warning">{stats.pending}</span>
          </div>
          <div className="stat-card text-center">
            <span className="stat-card-label">Contacted</span>
            <span className="stat-card-value">{stats.contacted}</span>
          </div>
          <div className="stat-card text-center">
            <span className="stat-card-label">Interested</span>
            <span className="stat-card-value">{stats.interested}</span>
          </div>
          <div className="stat-card text-center">
            <span className="stat-card-label">EOI</span>
            <span className="stat-card-value">{stats.eoi}</span>
          </div>
          <div className="stat-card text-center">
            <span className="stat-card-label">Booked</span>
            <span className="stat-card-value text-success">{stats.booked}</span>
          </div>
          </div>
        </div>

        <div className="toolbar border-b border-hairline rounded-none border-x-0 border-t-0">
          <div className="toolbar-left">
            <input
              type="text"
              placeholder="Search by name, number..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="input-field text-xs sm:max-w-xs"
            />
            <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="select-field text-xs w-36">
              <option value="">All Statuses</option>
              <option value="Pending">Pending</option>
              <option value="Contacted">Contacted</option>
              <option value="Interested">Interested</option>
              <option value="EOI">EOI</option>
              <option value="Booked">Booked</option>
            </select>
            <input type="text" placeholder="Project..." value={projectFilter} onChange={(e) => setProjectFilter(e.target.value)} className="input-field text-xs w-28" />
          </div>
          <div className="toolbar-right">
            <button onClick={() => setShowIngestModal(true)} className="btn-primary btn-sm">
              <Plus className="w-4 h-4" /> Ingest Lead
            </button>
          </div>
        </div>

        {/* Leads List */}
        <div className="flex-1 overflow-y-auto">
          <div className="divide-y divide-hairline-soft">
            {loading && leads.length === 0 ? (
              <div className="text-center py-20">
                <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-ink mx-auto"></div>
              </div>
            ) : leads.length === 0 ? (
              <div className="text-center py-20 text-muted text-sm">
                No active leads found matching criteria.
              </div>
            ) : (
              leads.map((lead) => (
                <div
                  key={lead._id}
                  onClick={() => handleOpenChat(lead)}
                  className={`p-4 hover:bg-surface-soft/50 cursor-pointer flex items-center justify-between transition-all ${selectedLead?._id === lead._id ? 'bg-surface-soft/20 border-l-4 border-ink' : ''}`}
                >
                  <div className="space-y-1.5 min-w-0 flex-1 pr-4">
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-ink truncate">{lead.name}</span>
                      {lead.unread_wa_count > 0 && (
                        <span className="bg-emerald-500 text-white font-semibold text-xxs px-2 py-0.5 rounded-full animate-bounce">
                          {lead.unread_wa_count} new
                        </span>
                      )}
                    </div>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-muted text-xs font-semibold">
                      <span className="flex items-center gap-1"><Phone className="w-3.5 h-3.5" /> {lead.number}</span>
                      <span className="flex items-center gap-1 text-body"><Layers className="w-3.5 h-3.5" /> {lead.project || 'General'}</span>
                      <span className="flex items-center gap-1 text-muted"><User className="w-3.5 h-3.5" /> {getAssigneeName(lead.assign_to_user)}</span>
                    </div>
                  </div>

                  <div className="flex items-center gap-3">
                    <select
                      value={lead.status}
                      onClick={(e) => e.stopPropagation()}
                      onChange={(e) => handleUpdateStatus(lead._id, e.target.value)}
                      className={`badge-pill uppercase text-[10px] ${lead.status === 'Pending' ? 'text-warning' : lead.status === 'Booked' ? 'text-success' : ''}`}
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
        <div className="p-3 border-t border-hairline-soft flex items-center justify-between bg-surface-soft/50">
          <button
            onClick={() => setPage(Math.max(1, page - 1))}
            disabled={page === 1}
            className="px-3 py-1.5 border border-hairline rounded-lg text-xs font-semibold disabled:opacity-50"
          >
            Prev
          </button>
          <span className="text-xs font-semibold text-muted">Page {page} of {totalPages}</span>
          <button
            onClick={() => setPage(Math.min(totalPages, page + 1))}
            disabled={page === totalPages}
            className="px-3 py-1.5 border border-hairline rounded-lg text-xs font-semibold disabled:opacity-50"
          >
            Next
          </button>
        </div>
      </div>

      {/* Right Detail & Chat Sidebar */}
      {selectedLead && showChat && (
        <div className="w-full xl:w-96 flex flex-col border border-hairline-soft bg-canvas rounded-lg shadow-sm overflow-hidden h-full">
          {/* Header */}
          <div className="p-4 border-b border-hairline-soft flex items-center justify-between bg-surface-dark text-white">
            <div className="min-w-0">
              <h4 className="font-semibold text-sm truncate">{selectedLead.name}</h4>
              <p className="text-xxs text-muted flex items-center gap-1 mt-0.5">
                <Phone className="w-3 h-3" /> {selectedLead.number}
              </p>
            </div>
            <button onClick={() => setShowChat(false)} className="p-1 hover:bg-surface-dark-elevated rounded-lg">
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Details Scroll */}
          <div className="flex-1 overflow-y-auto p-4 space-y-6">
            
            {/* Assignee Selection */}
            <div className="p-3 bg-slate-50 border border-slate-100 rounded-xl flex items-center justify-between">
              <span className="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                <User className="w-4 h-4 text-brand-500" /> Lead Assignee
              </span>
              <select
                value={selectedLead.assign_to_user || 'unassigned'}
                disabled={!['superuseradmin', 'hradmin', 'manager', 'business head'].includes(user.user_type)}
                onChange={(e) => handleUpdateAssignee(selectedLead._id, e.target.value)}
                className="px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-bold focus:outline-none focus:border-brand-500 disabled:opacity-75 disabled:bg-slate-100 text-slate-800"
              >
                <option value="unassigned">Unassigned</option>
                {activeUsers.map((u) => (
                  <option key={u._id} value={u.tablename}>
                    {u.username} ({u.user_type})
                  </option>
                ))}
              </select>
            </div>

            {/* Auto reply switch */}
            <div className="p-3 bg-surface-soft/20 border border-ink/10 rounded-lg flex items-center justify-between">
              <span className="text-xs font-semibold text-body flex items-center gap-1.5">
                <Sparkles className="w-4 h-4 text-accent" /> Esha AI Autoreply
              </span>
              <button onClick={handleToggleAutoReply} className="text-accent focus:outline-none">
                {autoReply ? <ToggleRight className="w-10 h-10" /> : <ToggleLeft className="w-10 h-10 text-muted" />}
              </button>
            </div>

            {/* Chat Messages */}
            <div className="space-y-4">
              <h5 className="text-muted text-xxs uppercase font-semibold tracking-wider">WhatsApp Transcript</h5>
              <div ref={chatContainerRef} className="border border-hairline-soft rounded-lg p-3 bg-surface-soft/50 h-64 overflow-y-auto space-y-2 flex flex-col">
                {chatLoading ? (
                  <div className="animate-spin rounded-full h-6 w-6 border-t-2 border-ink mx-auto my-auto"></div>
                ) : chatMessages.length === 0 ? (
                  <div className="text-center text-xs text-muted my-auto">No WhatsApp logs recorded.</div>
                ) : (
                  chatMessages.map((msg, idx) => (
                    <div
                      key={idx}
                      className={`max-w-[75%] p-2.5 rounded-lg text-xs ${msg.role === 'esha' ? 'bg-primary text-white self-end rounded-tr-none' : 'bg-surface-strong text-ink self-start rounded-tl-none'}`}
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
                  className="flex-1 px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs"
                />
                <button
                  type="submit"
                  className="p-2 bg-primary hover:bg-primary-active text-white rounded-lg transition-all"
                >
                  <Send className="w-4 h-4" />
                </button>
              </form>
            </div>

            {/* Remarks Section */}
            <div className="space-y-4 border-t border-hairline-soft pt-4">
              <h5 className="text-muted text-xxs uppercase font-semibold tracking-wider">Activity Remarks</h5>
              
              <div className="space-y-2 max-h-40 overflow-y-auto pr-1">
                {selectedLead.remarks && selectedLead.remarks.length > 0 ? (
                  selectedLead.remarks.map((rmk, idx) => (
                    <div key={idx} className="p-2.5 bg-surface-soft border border-hairline-soft rounded-lg text-xs">
                      <p className="text-ink">{rmk.text}</p>
                      <div className="flex justify-between text-xxs text-muted mt-1 font-semibold">
                        <span>By: {rmk.created_by}</span>
                        <span>{new Date(rmk.created_at).toLocaleDateString()}</span>
                      </div>
                    </div>
                  ))
                ) : (
                  <p className="text-center text-xs text-muted">No remarks logged.</p>
                )}
              </div>

              <form onSubmit={handleAddRemark} className="space-y-2">
                <textarea
                  placeholder="Add a progress remark..."
                  value={remarkText}
                  onChange={(e) => setRemarkText(e.target.value)}
                  className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs h-16 resize-none"
                />
                <button
                  type="submit"
                  className="w-full py-2 bg-surface-dark-elevated hover:bg-surface-dark text-white rounded-lg text-xs font-semibold transition-all"
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
        <div className="fixed inset-0 bg-surface-dark/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-canvas border border-hairline rounded-lg w-full max-w-md shadow-elevated p-6 space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-ink font-semibold text-base">Manual Lead Ingestion</h3>
              <button onClick={() => setShowIngestModal(false)} className="p-1 hover:bg-surface-soft rounded-lg text-muted">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleIngestSubmit} className="space-y-4">
              <div>
                <label className="block text-body text-xs font-semibold mb-1">Lead Name *</label>
                <input
                  type="text"
                  required
                  value={ingestForm.name}
                  onChange={(e) => setIngestForm({ ...ingestForm, name: e.target.value })}
                  className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs"
                />
              </div>
              <div>
                <label className="block text-body text-xs font-semibold mb-1">Lead Number (10 digits) *</label>
                <input
                  type="text"
                  required
                  value={ingestForm.number}
                  onChange={(e) => setIngestForm({ ...ingestForm, number: e.target.value })}
                  className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs"
                />
              </div>
              <div>
                <label className="block text-body text-xs font-semibold mb-1">Email Address</label>
                <input
                  type="email"
                  value={ingestForm.email}
                  onChange={(e) => setIngestForm({ ...ingestForm, email: e.target.value })}
                  className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs"
                />
              </div>
              <div>
                <label className="block text-body text-xs font-semibold mb-1">Location</label>
                <input
                  type="text"
                  value={ingestForm.location}
                  onChange={(e) => setIngestForm({ ...ingestForm, location: e.target.value })}
                  className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs"
                />
              </div>
              <div>
                <label className="block text-body text-xs font-semibold mb-1">Project Name *</label>
                <input
                  type="text"
                  required
                  value={ingestForm.project_name}
                  onChange={(e) => setIngestForm({ ...ingestForm, project_name: e.target.value })}
                  className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs"
                />
              </div>
              <div>
                <label className="block text-body text-xs font-semibold mb-1">Subsource of Lead</label>
                <input
                  type="text"
                  value={ingestForm.subsource_of_lead}
                  onChange={(e) => setIngestForm({ ...ingestForm, subsource_of_lead: e.target.value })}
                  className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs"
                />
              </div>

              <div className="pt-2 flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setShowIngestModal(false)}
                  className="px-4 py-2 border border-hairline text-body rounded-lg hover:bg-surface-soft text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-primary text-white rounded-lg text-xs font-semibold hover:bg-primary-active"
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
