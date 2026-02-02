import json
import mysql.connector
import os

# Configuration de la connexion MariaDB
config = {
    'user': 'admin',
    'password': 'admin',
    'host': '127.0.0.1',
    'database': 'moussaillons',
}

def import_data():
    # 1. Vérifier si le fichier existe
    if not os.path.exists('data.json'):
        print("Erreur : Le fichier 'data.json' est introuvable dans ce dossier.")
        return

    # 2. Lire le fichier JSON
    try:
        with open('data.json', 'r', encoding='utf-8') as f:
            pb_data = json.load(f)
    except json.JSONDecodeError as e:
        print(f"Erreur de lecture du JSON : {e}")
        return

    conn = None
    try:
        # 3. Connexion à MariaDB
        conn = mysql.connector.connect(**config)
        cursor = conn.cursor()

        print(f"Connexion réussie. Début de l'import de {len(pb_data['items'])} éléments...")

        for item in pb_data['items']:
            # Mapping PocketBase -> MariaDB
            matiere = item.get('univers', 'Inconnu')
            theme = item.get('rubrique', 'Divers')
            title = item.get('titre', 'Sans titre')
            content_html = item.get('contenu', '')
            
            # Conversion de l'objet quiz_json en texte pour LONGTEXT
            # ensure_ascii=False permet de garder les accents français lisibles
            quiz_json_str = json.dumps(item.get('quiz_json', []), ensure_ascii=False)
            
            # Valeurs fixes
            distance = 0
            is_public = 1
            is_validated = 1
            author_id = 1 

            sql = """INSERT INTO activities 
                     (matiere, theme, title, distance, content_html, quiz_json, is_public, is_validated, author_id) 
                     VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)"""
            
            val = (matiere, theme, title, distance, content_html, quiz_json_str, is_public, is_validated, author_id)
            
            cursor.execute(sql, val)

        # 4. Validation finale
        conn.commit()
        print(f"Succès ! {len(pb_data['items'])} activités ont été insérées dans 'moussaillons'.")

    except mysql.connector.Error as err:
        print(f"Erreur MariaDB : {err}")
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()
            print("Connexion fermée.")

if __name__ == "__main__":
    import_data()
