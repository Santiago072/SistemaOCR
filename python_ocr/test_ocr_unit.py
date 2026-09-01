"""Pruebas unitarias para el motor de cotejo, cálculo de edad y normalización de textos."""
import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from campos import calcular_edad, limpiar_texto, compacto, parecido
from listado import separar_nombre


class TestCamposOCR(unittest.TestCase):

    def test_calcular_edad(self):
        self.assertIsNotNone(calcular_edad("15/05/1995"))
        self.assertGreater(calcular_edad("15/05/1995"), 18)
        self.assertIsNone(calcular_edad(""))
        self.assertIsNone(calcular_edad("fecha_invalida"))

    def test_limpiar_texto(self):
        raw = "  sAnTIaGo   CARVAJAL  "
        clean = limpiar_texto(raw)
        self.assertEqual(clean, "SANTIAGO CARVAJAL")

    def test_compacto(self):
        self.assertEqual(compacto("1.117.811.433"), "1117811433")
        self.assertEqual(compacto("  c.c. 1117 "), "CC1117")

    def test_parecido(self):
        sim_alta = parecido("SANTIAGO CARVAJAL", "SANTIAGO CARBAJAL")
        sim_baja = parecido("SANTIAGO", "MARIA JOSE")
        self.assertGreater(sim_alta, 0.85)
        self.assertLess(sim_baja, 0.60)

    def test_separar_nombre_con_pista(self):
        completo = "LEIDY YURITZA SANTOS MANRIQUE"
        pista_ape = "SANTOS MANRIQUE"
        nom, ape = separar_nombre(completo, pista_ape)
        self.assertIn("LEIDY", nom)
        self.assertIn("SANTOS", ape)

    def test_separar_nombre_cuatro_palabras(self):
        completo = "ARLINSON DANIEL MONTOYA MANCERA"
        nom, ape = separar_nombre(completo)
        self.assertEqual(nom, "ARLINSON DANIEL")
        self.assertEqual(ape, "MONTOYA MANCERA")


if __name__ == "__main__":
    unittest.main()
