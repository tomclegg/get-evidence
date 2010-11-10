import httplib

from config import WAREHOUSE_CONTROLLER, WAREHOUSE_CONFIGURL

def name_lookup (name):
    conn = httplib.HTTPConnection (WAREHOUSE_CONTROLLER)
    conn.request("POST", "/get", name)
    resp = conn.getresponse()
    if resp.status:
        data = resp.read().split(" ", 3)
        if data[0] == "200":
            conn.close()
            return data[1]
        if data[0] == "404":
            conn.close()
            return None
        conn.close()
        raise RuntimeError("Controller communication error: %s" % data[0])
    conn.close()
    raise RuntimeError("Controller communcation error: %d %s" % (resp.status, resp.reason))

