import apiFetch from "@wordpress/api-fetch";

const { render, useState, useEffect } = wp.element;

const Plugins = () => {
  const [plugins, setPlugins] = useState([]);

  useEffect(async () => {
    const pluginResponse = await apiFetch({
      path: "/smolblog/v2/admin/plugins",
    });

    setPlugins(pluginResponse);
  }, []);

  return (
    <table className="widefat striped fixed">
      <colgroup>
        <col span="1" style={{ width: "150px", fontFace: "monospaced" }} />
        <col span="1" style={{ width: "150px" }} />
        <col span="1" />
      </colgroup>
      <thead>
        <tr>
          <th>Package</th>
          <th>Name</th>
          <th>Description</th>
        </tr>
      </thead>

      <tbody>
        {plugins.map((plugin) => (
          <tr>
            <td>{`${plugin.package}: ${plugin.version}`}</td>
            <td>{plugin.title}</td>
            <td>{plugin.description}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
};

export default Plugins;
