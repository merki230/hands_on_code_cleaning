const bcrypt = require("bcryptjs");
const models = require("../models/index");
const User = models.User;
export default {
  index: async (req, res) => {
    try {
      const users = await User.findAll({
        include: ["role", "promoter"],
        attributes: { exclude: ["password"] },
      });
      res.status(200).json(users);
    } catch (error) {
      res.status(500).json(error);
    }
  },
  store: (req, res) => {
    try {
      // Il faut juste faire attention parfois avec l'utilisation de callback du coté serveur, ça peut amener les éffets de bord 
      bcrypt.genSalt(10, (err, salt) => {
        if (err) {
          console.log(err);
        }
        bcrypt.hash(req.body.password, salt, async (err, hashedPassword) => {
          if (err) {
            console.log(err);
          }
          const data = {
            ...req.body,
            password: hashedPassword,
          };
          const user = await User.create(data, { fields: User.fillable });
          res.status(201).json(user);
        });
      });
    } catch (error) {
      res.status(500).json(error);
    }
  },
  show: async (req, res) => {
    try {
      const id = req.param("id");
      const user = await User.findByPk(id, {
        attributes: { exclude: ["password"] },
      });
      res.status(200).json(user);
    } catch (error) {
      res.status(500).json(error);
    }
  },
  getUserBypromoter: async (req, res) => {
    try {
      const id = req.param("id");
      const users = await User.findAll({
        where: {
          promoterId: id,
        },
        attributes: { exclude: ["password"] },
        include: ["role", "promoter"],
      });
      res.status(200).json(users);
    } catch (error) {
      res.status(500).json(error);
    }
  },
  update: (req, res) => {
    try {
      const id = req.param("id");
      bcrypt.genSalt(10, (err, salt) => {
        if (err) {
          console.log(err);
        }
        bcrypt.hash(req.body.password, salt, async (err, hashedPassword) => {
          if (err) {
            console.log(err);
          }
          const data = {
            ...req.body,
            password: hashedPassword,
          };
          const user = await User.update(data, {
            where: {
              id,
            },
            fields: User.fillable,
          });
          res.status(200).json(user);
        });
      });
    } catch (error) {
      res.status(500).json(error);
    }
  },
  delete: async (req, res) => {
    try {
      const id = req.param("id");
      const user = await User.destroy({
        where: { id },
      });
      res.status(204).json(user);
    } catch (error) {
      res.status(500).json(error);
    }
  },
};
