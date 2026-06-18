import express from 'express';
import Asset from '../models/Asset.js';
import AssetAssignment from '../models/AssetAssignment.js';
import { protect, adminOnly } from '../middleware/auth.js';

const router = express.Router();

// Admin: Get asset registry list
router.get('/', protect, adminOnly, async (req, res) => {
  try {
    const { search, status, type } = req.query;
    let query = {};
    
    if (status) query.status = status;
    if (type) query.type = type;
    if (search) {
      query.$or = [
        { name: { $regex: search, $options: 'i' } },
        { serialNumber: { $regex: search, $options: 'i' } },
        { type: { $regex: search, $options: 'i' } }
      ];
    }

    const assets = await Asset.find(query).sort({ createdAt: -1 });
    res.json(assets);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Add asset to registry
router.post('/', protect, adminOnly, async (req, res) => {
  try {
    const { name, type, serialNumber } = req.body;
    if (!name || !type || !serialNumber) {
      return res.status(400).json({ message: 'All fields are required.' });
    }

    const serialExists = await Asset.findOne({ serialNumber });
    if (serialExists) {
      return res.status(400).json({ message: 'Asset with this serial number already exists.' });
    }

    const asset = new Asset({ name, type, serialNumber, status: 'Available' });
    await asset.save();
    res.status(201).json(asset);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Get active assignments
router.get('/assignments', protect, adminOnly, async (req, res) => {
  try {
    const assignments = await AssetAssignment.find({ returnedDate: { $exists: false } })
      .populate('asset')
      .populate('user', 'username employee_id user_type project_name')
      .sort({ assignedDate: -1 });
    res.json(assignments);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Assign an asset to an employee
router.post('/assign', protect, adminOnly, async (req, res) => {
  try {
    const { assetId, userId, notes } = req.body;
    if (!assetId || !userId) {
      return res.status(400).json({ message: 'Asset ID and User ID are required.' });
    }

    const asset = await Asset.findById(assetId);
    if (!asset) return res.status(404).json({ message: 'Asset not found.' });
    if (asset.status === 'Assigned') {
      return res.status(400).json({ message: 'Asset is already assigned.' });
    }

    // Create assignment log
    const assignment = new AssetAssignment({
      asset: assetId,
      user: userId,
      notes,
      assignedDate: new Date()
    });
    await assignment.save();

    // Mark asset status as Assigned
    asset.status = 'Assigned';
    await asset.save();

    res.json({ message: 'Asset assigned successfully.', data: assignment });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Return an asset assignment
router.post('/return/:id', protect, adminOnly, async (req, res) => {
  try {
    const assignment = await AssetAssignment.findById(req.params.id);
    if (!assignment) return res.status(404).json({ message: 'Assignment record not found.' });
    if (assignment.returnedDate) {
      return res.status(400).json({ message: 'Asset has already been returned.' });
    }

    // Close assignment log
    assignment.returnedDate = new Date();
    await assignment.save();

    // Mark asset status as Available
    const asset = await Asset.findById(assignment.asset);
    if (asset) {
      asset.status = 'Available';
      await asset.save();
    }

    res.json({ message: 'Asset returned successfully.', data: assignment });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
